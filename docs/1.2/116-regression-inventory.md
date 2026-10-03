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
| CSV, REST and WXR | Runtime probes retain published-only four-column CSV query rows, core GET /wp/v2/cleanlinks, CPT capabilities and can_export=false. Existing tests pass for formula hardening, generated WXR privacy, counter metadata hooks, cache invalidation and redirect filters. | Native authenticated admin export role/nonce matrix and final release matrix. CSV is not a full backup. |
| Package contents | Assets rebuild using existing dependencies. Local ZIP passes the validator and runs from extraction. Negative ZIP tests reject docs, DESIGN.md, test files and missing shared-command runtime. Local patch excludes development docs and requires new runtime files. | Authoritative package builder with a fresh production Composer dependency stage; Plugin Check if available. No dependency installations were authorized in this lane. |
| Large datasets | Relevant existing 205-row and 10,000-row export tests pass bounded query assertions and exact IDs. | Import preview/jobs/restart/cancel/idempotency/concurrent modification and malformed-file/encoding proof belong to #53. Export proof does not cover import. |
| UI and accessibility | No production UI or screenshot work performed. | #74 reference/design approval, then #50/#51/#53/#55/#56 and desktop/narrow/keyboard proof. |
| Backend commands / rejected saves | Reuse #117 exact-head green checks and its rejection, session, capability, retry, recovery and MySQL snapshot evidence. | Rerun after any relevant implementation change, not as an unrelated quota-consuming repetition. |

## Measured limits

Environment: WordPress 7.1.2, PHP 8.5.10, SQLite adapter 1.8.0, author-run local fixtures. The selected suite passed 29 tests / 10,094 assertions in 40.061 seconds with a 94.50 MB whole-suite peak reported by PHPUnit. JUnit reports 0.748 seconds for the 205-row test and 39.066 seconds for the 10,000-row test. Those timings include fixture creation, not just export streaming. Assertions require fewer than 20 and 250 export queries respectively; these are verified bounds, not reported exact query counts or proposed production budgets. No 10,000-row import claim is made.

The final extracted-package HTTP run passed nine aggregate probes and 82 HTTP requests. Requests use only a fresh loopback fixture, permit at most three local canonical hops, stop at the destination Location, block WordPress outbound HTTP, and verify the destination-contact sentinel remains absent. The user site is never booted or edited.

Local snapshot ZIP SHA-256: `f1773806995a580a2c342a485f1f38b92da5f436cf301eb14ed20e3a1cb31732`. Its runtime payload is #117 backend code; only package exclusions change. Runtime Composer files were reused from the existing validated candidate ZIP, rather than installing new dependencies. This is explicitly a preparation artifact, not an authoritative release build.

## Repeat the proof

Run `tests/runtime/upgrade-http.py` with `--wordpress-source` pointing to the existing disposable SQLite WordPress copy, `--production` and `--candidate` source snapshots with runtime autoload files, `--target` pointing to the backend checkout or extracted ZIP, a fresh `--fixture` directory, and `--evidence` output. The script refuses to overwrite an existing fixture. Bind only loopback; never follow destination URLs. Run `python3 tests/runtime/package-regressions.py /path/to/snapshot.zip` to test the exact archive validator.

The existing authoritative command remains `npm run package:release -- /path/to/release.zip` when fresh local production dependency staging is authorized. No tag, prerelease, release, upload, remote PR, merge or user-site change was performed for this lane.

## Local change and rollback

The local patch adds two fixture runners and archive negative tests. `.distignore` now excludes `/docs` and `DESIGN.md`; the ZIP validator rejects those entries and requires the shared-command runtime files. The initial snapshot had shipped docs/1.2/114-foundation.md and the prior validator accepted it. The production data/redirect implementation needed no fix in the tested cases. Reverting this local test/package patch leaves the completed #117 backend unchanged.
