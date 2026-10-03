# Cache proof

`cache-isolation.py` exercises real WordPress subprocesses against the disposable
`wordpress_tests` database. The nonpersistent lane pauses create/update saves in
`save_post_cleanlinks`, reads through a second ordinary WordPress process, and
checks committed state, writer-hook state, terms, commit and rollback.

The Redis lane uses PhpRedis and the upstream `rhubarbgroup/redis-cache`
drop-in pinned to commit `9b498638aac5789b9a339d98360904e67f7290e1`. It uses
Redis database 15 with a run-specific prefix and never issues cache flushes.
With the isolation guard enabled, command create/update/read/batch operations
must fail closed before save hooks, rows, terms, metadata, or receipts change.
The legacy metadata form path is checked separately for valid nonce, invalid
nonce, and unauthorized user behavior.

The optional historical baseline is intentionally red against the fixed code.
To reproduce the known leak, run this harness from the pre-guard snapshot
`57caf70d9bb6dc7bcdfaf8fc0b7546ac14cda1df` with a disposable runtime and
`CLEANLINKS_CACHE_BACKEND=redis CLEANLINKS_CACHE_MODE=baseline
CLEANLINKS_REDIS_CACHE=1 CLEANLINKS_REDIS_PREFIX=clproof_local_baseline`.
Set `CLEANLINKS_RUNTIME_CONFIG` to that runtime's isolated WordPress test
config. Never point this proof at a development, staging, or production site.
