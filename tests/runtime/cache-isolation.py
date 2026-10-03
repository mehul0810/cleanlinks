"""Exercise ordinary cross-process WordPress readers during a paused command save."""
import json
import atexit
import os
import shutil
import subprocess
import tempfile
import time
from pathlib import Path

REQUEST = str(Path(__file__).with_name("cache-request.php"))
CACHE_CONFIG = str(Path(__file__).with_name("cache-config.php"))
RUN = os.getenv("GITHUB_RUN_ID", str(time.time_ns()))
TEMP = Path(tempfile.mkdtemp(prefix="cleanlinks-cache-"))
atexit.register(shutil.rmtree, TEMP, ignore_errors=True)


def invoke(payload):
    env = os.environ.copy()
    result = subprocess.run(["php", "-d", f"auto_prepend_file={CACHE_CONFIG}", REQUEST], input=json.dumps(payload), text=True,
                            capture_output=True, timeout=30, env=env)
    assert result.returncode == 0, (result.returncode, result.stderr)
    return json.loads(result.stdout.strip().splitlines()[-1])


def read(id):
    return invoke({"mode": "read", "id": id})


def write(body, method="POST", route="/cleanlinks/v1/links", rollback=False):
    checkpoint = TEMP / "checkpoint.json"
    release = TEMP / "release"
    checkpoint.unlink(missing_ok=True)
    release.unlink(missing_ok=True)
    env = os.environ.copy()
    env["CLEANLINKS_CACHE_CHECKPOINT"] = str(checkpoint)
    env["CLEANLINKS_CACHE_RELEASE"] = str(release)
    writer = subprocess.Popen(["php", "-d", f"auto_prepend_file={CACHE_CONFIG}", REQUEST], stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                              stderr=subprocess.PIPE, text=True, env=env)
    writer.stdin.write(json.dumps({"mode": "write", "body": body, "method": method,
                                   "route": route, "rollback": rollback}))
    writer.stdin.close()
    writer.stdin = None
    deadline = time.monotonic() + 18
    while not checkpoint.exists() and writer.poll() is None and time.monotonic() < deadline:
        time.sleep(0.01)
    if not checkpoint.exists():
        stdout, stderr = writer.communicate(timeout=2)
        raise AssertionError(f"Writer did not reach save_post_cleanlinks checkpoint: {stdout} {stderr}")
    own = json.loads(checkpoint.read_text())
    visible = read(own["id"])
    release.touch()
    stdout, stderr = writer.communicate(timeout=20)
    assert writer.returncode == 0, (writer.returncode, stderr)
    result = json.loads(stdout.strip().splitlines()[-1])
    assert result["status"] == (500 if rollback else 200), result
    return own, visible, result


def command(title, slug, destination, key):
    nonce = str(time.time_ns())[-16:]
    return {"title": title, "slug": slug, "destination": destination, "status": "publish",
            "request_key": f"{int(time.time())}_{key}_{nonce}"}


def expect_committed(actual, expected):
    assert actual["exists"] is expected["exists"], actual
    assert actual["title"] == expected["title"], actual
    assert actual["destination"] == expected["destination"], actual
    assert actual["groups"] == expected["groups"], actual


suffix = os.getenv("CLEANLINKS_CACHE_BACKEND", "nonpersistent")
mode = os.getenv("CLEANLINKS_CACHE_MODE", "fixed")
initial_url = f"https://example.org/{suffix}-old"
changed_url = f"https://example.org/{suffix}-new"
base = command("Committed title", f"cache-{suffix}-{RUN}", initial_url, f"cache_create_{suffix}")
group_ids = invoke({"mode": "groups", "slug": base["slug"]})
base["groups"] = [group_ids["committed"]]
if suffix == "redis" and mode == "fixed":
    seeded = invoke({"mode": "seed", "title": base["title"], "slug": base["slug"],
                     "destination": base["destination"], "groups": base["groups"]})
    post_id = seeded["id"]
    canary_key = f"{RUN}_{time.time_ns()}"
    canary_value = f"redis-cache-proof:{RUN}"
    write_canary = invoke({"mode": "cache-write", "key": canary_key, "value": canary_value})
    read_canary = invoke({"mode": "cache-read", "key": canary_key})
    assert write_canary["saved"] and write_canary["external"], write_canary
    assert read_canary["external"] and read_canary["found"] and read_canary["value"] == canary_value, read_canary
    print(json.dumps({"case": "redis cross-process canary", "pass": True,
                      "write": write_canary, "read": read_canary}, sort_keys=True))
    read_rejected = invoke({"mode": "guard", "method": "GET", "route": f"/cleanlinks/v1/links/{post_id}"})
    assert read_rejected["status"] == 503 and read_rejected["data"]["code"] == "storage_unavailable", read_rejected
    print(json.dumps({"case": "redis guard read", "pass": True, "response": read_rejected}, sort_keys=True))
    read_state = invoke({"mode": "inspect", "id": post_id, "slug": base["slug"], "request_key": base["request_key"]})
    assert read_state["mutex"] is None, read_state
    for operation in ("create", "update"):
        body = command("Speculative " + operation, f"cache-{operation}-guard-{RUN}", changed_url,
                       f"cache_guard_{operation}")
        body["groups"] = [group_ids["speculative"]]
        if operation == "update":
            state = invoke({"mode": "version", "id": post_id})
            body = {"title": "Speculative update", "destination": changed_url,
                    "request_key": body["request_key"], "expected_version": state["version"],
                    "groups": [group_ids["speculative"]]}
        before = read(post_id)
        result = invoke({"mode": "guard", "body": body,
                         "method": "PATCH" if operation == "update" else "POST",
                         "route": f"/cleanlinks/v1/links/{post_id}" if operation == "update" else "/cleanlinks/v1/links"})
        assert result["status"] == 503, result
        assert result["data"]["code"] == "storage_unavailable", result
        assert result["save_hooks"] == 0, result
        after = read(post_id)
        assert after == before, (before, after)
        durable = invoke({"mode": "inspect", "id": post_id if operation == "update" else 0,
                          "slug": body["slug"] if operation == "create" else base["slug"],
                          "request_key": body["request_key"]})
        assert durable["receipt"] is None, durable
        assert durable["mutex"] is None, durable
        assert durable["slug_count"] == (1 if operation == "update" else 0), durable
        if operation == "update":
            assert durable["row"]["post_title"] == base["title"] and durable["destination"] == initial_url, durable
            assert durable["groups"] == [group_ids["committed"]], durable
        print(json.dumps({"case": "redis guard " + operation, "pass": True, "response": result,
                          "reader_before": before, "reader_after": after, "database": durable}, sort_keys=True))
    legacy_url = f"https://example.org/legacy-helper-{RUN}?a=1&b=%2F"
    legacy = invoke({"mode": "legacy-form", "id": post_id, "destination": legacy_url, "variant": "valid"})
    assert legacy["saved"] == legacy_url and legacy["nofollow"] == "1", legacy
    assert read(post_id)["destination"] == legacy_url
    for variant in ("invalid", "unauthorized"):
        rejected_url = f"https://example.org/legacy-{variant}-{RUN}"
        rejected = invoke({"mode": "legacy-form", "id": post_id, "destination": rejected_url, "variant": variant})
        assert rejected["saved"] == legacy_url, (variant, rejected)
    print(json.dumps({"case": "redis legacy form nonce/auth", "pass": True, "valid": legacy}, sort_keys=True))

    batch = command("Speculative batch", f"cache-batch-guard-{RUN}", changed_url, "cache_guard_batch")
    batch["groups"] = [group_ids["speculative"]]
    batched = invoke({"mode": "guard", "method": "POST", "route": "/cleanlinks/v1/link-commands",
                      "body": {"rows": [batch]}})
    assert batched["status"] == 200 and batched["data"][0]["error"]["code"] == "storage_unavailable", batched
    batch_state = invoke({"mode": "inspect", "slug": batch["slug"], "request_key": batch["request_key"]})
    assert batch_state["slug_count"] == 0 and batch_state["receipt"] is None and batch_state["mutex"] is None, batch_state
    print(json.dumps({"case": "redis guard execute_rows", "pass": True, "response": batched}, sort_keys=True))
    raise SystemExit(0)

if suffix == "redis" and mode == "baseline":
    seeded = invoke({"mode": "seed", "title": base["title"], "slug": base["slug"],
                     "destination": base["destination"], "groups": base["groups"]})
    post_id = seeded["id"]
    assert os.getenv("CLEANLINKS_REDIS_CACHE") == "1"
    body = command("Speculative title", base["slug"], changed_url, "cache_baseline_update")
    body["groups"] = [group_ids["speculative"]]
    state = invoke({"mode": "command", "method": "GET", "route": f"/cleanlinks/v1/links/{post_id}"})
    body["expected_version"] = state["data"]["version"]
    own, during, result = write(body, "PATCH", f"/cleanlinks/v1/links/{post_id}")
    assert result["status"] == 200 and own["hook_count"] == 1 and during["title"] == own["title"] and during["destination"] == own["destination"], (own, during, result)
    print(json.dumps({"case": "historical redis leak baseline", "pass": True, "writer": own,
                      "reader_during_save": during, "final": read(post_id)}, sort_keys=True))
    raise SystemExit(0)

if suffix == "redis":
    seeded = invoke({"mode": "seed", "title": base["title"], "slug": base["slug"],
                     "destination": base["destination"], "groups": base["groups"]})
    post_id = seeded["id"]
else:
    created = invoke({"mode": "command", "body": base})
    assert created["status"] == 200, created
    post_id = int(created["data"]["id"])

create_body = command("Speculative create", f"cache-create-{suffix}-{RUN}", changed_url,
                      f"cache_create_probe_{suffix}")
create_body["groups"] = [group_ids["speculative"]]
create_own, create_during, create_result = write(create_body)
assert create_own["title"] == create_body["title"] and create_own["destination"] == changed_url, create_own
assert create_own["hook_count"] == 1
expect_committed(create_during, {"exists": False, "title": None, "destination": "", "groups": []})
assert create_result["status"] == 200
new_id = int(create_result["data"]["id"])
created_final = read(new_id)
assert created_final["title"] == create_body["title"] and created_final["destination"] == changed_url
assert created_final["groups"] == [group_ids["speculative"]], created_final
print(json.dumps({"case": "create commit", "backend": suffix, "pass": True,
                  "writer": create_own, "reader_during_save": create_during,
                  "final": created_final}, sort_keys=True))

rollback_body = command("Rolled back create", f"cache-create-rollback-{suffix}-{RUN}", changed_url,
                        f"cache_create_rollback_{suffix}")
rollback_body["groups"] = [group_ids["speculative"]]
rollback_own, rollback_during, rollback_result = write(rollback_body, rollback=True)
expect_committed(rollback_during, {"exists": False, "title": None, "destination": "", "groups": []})
assert rollback_result["status"] == 500
assert not read(rollback_own["id"])["exists"], read(rollback_own["id"])
print(json.dumps({"case": "create rollback", "backend": suffix, "pass": True,
                  "writer": rollback_own, "reader_during_save": rollback_during}, sort_keys=True))

for name, title, destination, key, rollback in (
        ("update commit", "Speculative title", changed_url, "commit", False),
        ("update rollback", "Rolled back title", initial_url, "rollback", True)):
    state = invoke({"mode": "command", "method": "GET",
                    "route": f"/cleanlinks/v1/links/{post_id}"})
    assert state["status"] == 200, state
    body = command(title, base["slug"], destination, f"cache_update_{suffix}_{key}")
    body["expected_version"] = state["data"]["version"]
    body["groups"] = [group_ids["speculative"]]
    before = read(post_id)
    expected = {"exists": True, "title": before["title"], "destination": before["destination"],
                "groups": before["groups"]}
    own, during, result = write(body, "PATCH", f"/cleanlinks/v1/links/{post_id}", rollback)
    assert own["title"] == body["title"] and own["destination"] == destination, own
    assert own["hook_count"] == 1
    expect_committed(during, expected)
    if rollback:
        expect_committed(read(post_id), expected)
    else:
        assert result["data"]["title"] == title
        final = read(post_id)
        assert final["title"] == title and final["destination"] == destination, final
        assert final["groups"] == [group_ids["speculative"]], final
    print(json.dumps({"case": name, "backend": suffix, "pass": True,
                      "writer": own, "reader_during_save": during,
                      "response_status": result["status"], "final": read(post_id)}, sort_keys=True))
