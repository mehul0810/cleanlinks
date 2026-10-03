# Issue 116 regression preparation

This is local preparation for CleanLinks 1.2.0. It does not approve a release, close #116, or replace design approval. The packaged snapshot still reports version 1.1.2 because the 1.2 implementation is incomplete.

Source baselines verified on 2026-10-03:

- Production tag 1.1.1: `33d04c219906d9f66dd1915aacba47a800a91a88`.
- Reviewed #112 candidate: `4275febd509a3944d86f5bd3fa54678724b3bda7`.
- Completed #117 backend: `c37a3818cfd8057f5014ba085b6a69b1c7bbe4c1`.
- #118 remains a separate draft design contract. #116 is open, unassigned, and has no newer owner comment. No duplicate upgrade-proof PR was present.

## Evidence gathered

| Boundary | Current evidence | Remaining gate |
| --- | --- | --- |
| Stored data across upgrades | A copied SQLite fixture seeds ten records under production code, loads candidate and backend code, then rolls back to production. IDs, slugs, publish/draft/pending/private/future/trash/auto-draft states, raw destinations, nofollow, historical counts, groups and public URLs compare exactly. Repeated from the extracted ZIP. | Repeat against the final selected 1.2 package and a production-like backup. This is a code-swap fixture, not a browser-driven WordPress updater. |
| Public redirect responses | Real loopback HTTP requests cover plain, pretty and custom-base routes; plain-ID/plain-slug alternatives; incoming query parameters; exact 301 Location bytes, repeated parameters, percent encoding, plus signs and fragments; nofollow X-Robots-Tag; follow links; empty-destination 302 home fallback; filtered redirect hooks. Local canonical hops are recorded. | Final package on the supported WordPress/PHP matrix and MySQL. |
| Published-only counting | Anonymous requests to six non-published statuses never redirect to the stored destination and retain their counts. Four successful requests per published test link increase the historical count by four. The redirect action receives the count; 42 hook events are verified. | Persistent object cache and MySQL concurrent counter proof. The existing #117 MySQL command race proof concerns commands, not HTTP click counters. |
| CSV, REST and WXR | Runtime probes retain published-only four-column CSV query rows, core GET /wp/v2/cleanlinks, CPT capabilities and can_export=false. Existing tests pass for formula hardening, generated WXR privacy, counter metadata hooks, cache invalidation and redirect filters. | Distinct editor/author/contributor/subscriber actors and final release matrix. Administrator cookie-authenticated export, nonce/session denials and authenticated manage_options denial now pass (see continuation). CSV is not a full backup. |
| Package contents | Assets rebuild using existing dependencies. Local ZIP passes the validator and runs from extraction. Negative ZIP tests reject docs, DESIGN.md, test files and missing shared-command runtime. Local patch excludes development docs and requires new runtime files. | Authoritative builder now passes with a clean stage and existing cached production dependency; Plugin Check and final approved 1.2 payload remain. No dependency installations were authorized in this lane. |
| Large datasets | Relevant existing 205-row and 10,000-row export tests pass bounded query assertions and exact IDs. | Import preview/jobs/restart/cancel/idempotency/concurrent modification and malformed-file/encoding proof belong to #53. Export proof does not cover import. |
| UI and accessibility | No production UI or screenshot work performed. | #74 reference/design approval, then #50/#51/#53/#55/#56 and desktop/narrow/keyboard proof. |
| Backend commands / rejected saves | Reuse #117 exact-head green checks and its rejection, session, capability, retry, recovery and MySQL snapshot evidence. | Rerun after any relevant implementation change, not as an unrelated quota-consuming repetition. |

## Measured limits

Environment: WordPress 7.1.2, PHP 8.5.10, SQLite adapter 1.8.0, author-run local fixtures. The selected suite passed 29 tests / 10,094 assertions in 40.061 seconds with a 94.50 MB whole-suite peak reported by PHPUnit. JUnit reports 0.748 seconds for the 205-row test and 39.066 seconds for the 10,000-row test. Those timings include fixture creation, not just export streaming. Assertions require fewer than 20 and 250 export queries respectively; these are verified bounds, not reported exact query counts or proposed production budgets. No 10,000-row import claim is made.

The final extracted-package HTTP run passed nine aggregate probes and 82 HTTP requests. Requests use only a fresh loopback fixture, permit at most three local canonical hops, stop at the destination Location, block WordPress outbound HTTP, and verify the destination-contact sentinel remains absent. The user site is never booted or edited.

Local snapshot ZIP SHA-256: `f1773806995a580a2c342a485f1f38b92da5f436cf301eb14ed20e3a1cb31732`. Its runtime payload is #117 backend code; only package exclusions change. This first snapshot reused runtime Composer files from the existing validated candidate ZIP. A later authoritative builder run reconstructs a clean runtime stage; see its separate hash below. Both remain preparation artifacts for an incomplete 1.2 payload.

## Repeat the proof

Run `tests/runtime/upgrade-http.py` with `--wordpress-source` pointing to the existing disposable SQLite WordPress copy, `--production` and `--candidate` source snapshots with runtime autoload files, `--target` pointing to the backend checkout or extracted ZIP, a fresh `--fixture` directory, and `--evidence` output. The script refuses to overwrite an existing fixture. Bind only loopback; never follow destination URLs. Run `python3 tests/runtime/package-regressions.py /path/to/snapshot.zip` to test the exact archive validator.

The existing authoritative command remains `npm run package:release -- /path/to/release.zip` using existing cached locked dependencies with network disabled; no new software version, global install or download is required. No tag, prerelease, release, upload, remote PR, merge or user-site change was performed for this lane.

## Local change and rollback

The local patch adds two fixture runners and archive negative tests. `.distignore` now excludes `/docs` and `DESIGN.md`; the ZIP validator rejects those entries and requires the shared-command runtime files. The initial snapshot had shipped docs/1.2/114-foundation.md and the prior validator accepted it. The production data/redirect implementation needed no fix in the tested cases. Reverting this local test/package patch leaves the completed #117 backend unchanged.

## Independent continuation

Pass:

- The unmodified authoritative builder (`npm run package:release`) rebuilt assets, created a clean stage, extracted the already-cached locked composer/installers v2.3.0 dependency, optimized autoload files, and validated the exact ZIP. COMPOSER_DISABLE_NETWORK=1 was set; Composer/npm caches and Composer home were scoped to the test workspace or /tmp. No new software version, global installation, credential, or download was introduced. The unavailable online Composer filter feed is not a fresh dependency-security audit.
- The extracted authoritative ZIP passed all nine upgrade/HTTP probes and 82 loopback requests. This rerun was necessary because its clean Composer stage and optimized autoload files differ from the earlier reused-vendor snapshot. No #117 command suite was rerun.
- Real cookie-authenticated POST /wp-admin/admin-post.php exported CSV for the existing copied administrator. The four-column schema, published-only rows, formula prefix protection, quote escaping and Unicode survived. Seven denial cases passed: missing/invalid/expired nonce, authenticated actor without manage_options, anonymous request, expired auth cookie and revoked fixture session. No new account/password/API credential was created; the temporary session was created and revoked only in the copied fixture. The capability-denial case removes manage_options for that request through fixture instrumentation; it is not a distinct editor/subscriber principal.
- Exact archive negative tests reject docs, DESIGN.md, test content and missing shared-command runtime.

Fail: no remaining product or test failure in the executed cases. These passes do not imply the unrun gates passed.

Unrun:

- Distinct editor, author, contributor and subscriber accounts: the installed source fixture has only one administrator. No new users or credentials were created.
- Persistent WordPress object-cache integration: no installed WordPress cache adapter/drop-in was found in the existing CleanLinks disposable fixtures or checked development/plugin-test sites. Redis tooling exists, but no local 6379 listener or adapter fixture is available. No new adapter was installed or global service started.
- MySQL HTTP counter concurrency: no installed MySQL binary/service, 3306 listener, Docker binary or disposable local MySQL fixture was found. The completed hosted #117 command proof is different evidence and was not repeated. A sanctioned existing MySQL test fixture, or approval for a later scoped hosted test branch, is needed.
- Plugin Check and final UI/import/responsive/keyboard release checks remain unavailable or held by the existing design/implementation gates.
- Fresh quota: the installed official CodexBar CLI route reported no available Codex fetch strategy; its official web route was denied access to Safari/Firefox browser-cookie storage by macOS. Reading that storage would require access permission for /Applications/CodexBar.app/Contents/Helpers/CodexBarCLI and exposes browser session data. No Full Disk Access or security setting was changed. The earlier supplied 94% remaining snapshot was not replaced by an inferred value.

Authoritative preparation ZIP SHA-256: `d757bd5ac95122f0adbc8672157dc59e0c2a6af178545c27dd8dcc4360c46a75`. It still carries 1.1.2 metadata and must not be published as a 1.2 release. Evidence files: 116-authoritative-package-build.log, 116-authoritative-http-evidence.json, and 116-authenticated-export-evidence.json. Work remains local pending remote approval.
