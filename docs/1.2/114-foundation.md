# Shared link command contract — issue #114

Baseline: candidate/1.1.2-integrated at 4275febd509a3944d86f5bd3fa54678724b3bda7. Production UI/design work remains gated by #74.

## Application contract

LinkCommands accepts explicit unslashed create/update arrays. Supported fields: destination, title, slug, status, existing integer group IDs, boolean nofollow, request_key, and (updates) integer id + expected_version. confirm_slug_change=true is required to intentionally change an existing slug. Arbitrary post/meta fields, term creation, malformed lists/objects/scalars, forged IDs and unsupported statuses are rejected before mutation. Current supported statuses are draft/pending/publish/private; trash/restore remain a later #50 slice.

Every command requires an actor-scoped key `UNIX_TIMESTAMP_RANDOM_SUFFIX`: a 10-digit UTC Unix timestamp and 16–64-character alphanumeric/underscore/hyphen suffix. Keep the exact key/input for retries. Keys expire after seven days independently of receipt presence; future issuance beyond five minutes rejects. An expired uncertain request must be reconciled against existing records before creating a fresh command. Changing a key on an uncertain retry can create a duplicate.

Identical commands return the saved receipt without another save; changed input rejects with request_key_conflict. Receipts are private, non-autoloaded options committed atomically with the link. Capacity is 20,000 receipts per actor and 100,000 globally, including expired backlog until removed. Mutations prune at most 200 expired receipts and hourly maintenance at most 2,000; unavailable cleanup/storage fails closed and full capacity rejects before mutation. Uninstall removes owned receipts, mutex and cleanup schedule. Expiry is checked before lookup and again under the writer lock, so cleanup cannot enable delayed replay. No new credential or custom table is introduced.

read(id) returns the actual ID/title/slug/status/destination/nofollow/groups/public URL plus a version hash. Updates require this token; stale snapshots reject with stale_version. Counts are excluded from the token so clicks do not prevent editing. Duplicate explicit or derived slugs reject with slug_conflict rather than overwriting or silently renaming another record; draft slugs are checked too. Callers can propose a new slug and resubmit with a new key. Successful results always reflect persisted identity/state.

execute_rows accepts 1–50 row commands with independent stable keys. Each result has a 1-based row plus saved data or an error code and field/status. Valid rows can commit despite invalid peers; resubmitting returns their receipts. This is the bounded command primitive, not a CSV parser, preview UI, background import job or transactional bulk undo (#53).

## Persistence and compatibility

Keep the cleanlinks CPT and cleanlink_redirect_url/cleanlink_redirect_nofollow metadata. Lifetime counts, groups, existing IDs, public routing, extension filters and export schema remain. Destination safety uses the current UrlValidator without HTML output escaping; raw query separators, repeated parameters, percent encoding and plus signs survive storage. Core persists only owned metadata/terms before save_post hooks; full command saves skip the legacy request-global adapter.

A database transaction and permanent non-autoloaded mutex option serialize full application commands. Atomic create/update/read commands require the exact native WordPress request-local object-cache class and core cache functions, verified by source path; persistent drop-ins, subclasses, and other unqualified implementations fail closed with storage_unavailable before command hooks or writes. The transaction probes actual savepoint state, preserves caller-owned transactions, locks the update post and collision query, checks readback, and rolls back partial writes. Post/meta/object-term/term caches are invalidated after rollback. MySQL requires InnoDB for mutable tables; SQLite integration must support the tested transaction/savepoint semantics. A caller-owned transaction must be committed by that caller before its result is presented as durable.

The nonce-protected legacy editor metadata adapter always retains ordinary validated WordPress metadata updates and readback, without an atomic rollback guarantee. It does not probe cache provenance, storage engines, or transaction support, so existing editor saves remain available on installations that do not support command transactions. Full create/update commands skip this adapter and serialize only their own command writes through the transaction/mutex path.

Standard WordPress persistence hooks still fire. Database rollback cannot undo external effects performed by third-party hooks, nor control arbitrary direct SQL writers that bypass the service. Extension hooks that commit/DDL inside a full command save are unsupported. Cross-process mutex proof covers full service writers only; legacy editor saves and arbitrary third-party creation paths are not serialized by that mutex. Persistent-cache installations can use full commands only after a separately designed request-local isolation strategy; the legacy editor remains available without an atomicity guarantee.

## Access and transport

CPT capabilities come from the registered post type: create_posts; object-level edit_post for own/others; publish_posts for publish/private. Existing group assignment uses the taxonomy assign_terms capability (default edit_posts); group administration stays manage_categories. Existing export stays manage_options, published-only. No access is widened.

REST routes: POST /cleanlinks/v1/links, GET/PATCH/PUT /cleanlinks/v1/links/{id}, POST /cleanlinks/v1/link-commands with a rows envelope. Each permission callback requires an authenticated current user and an X-WP-Nonce for wp_rest, plus create/object-edit permission for single commands; batch rows receive individual application authorization; the application rechecks publication/group/object authority. Identity belongs to the route; an id in a single-save JSON body is rejected. No arbitrary post/meta mutation endpoint, destination fetch, production UI or navigation is added. Trusted in-process/cron callers set an explicit WordPress actor and call the application; request globals and DOING_AJAX/DOING_CRON do not control its persistence.

## Evidence and scope

Disposable WordPress 7.1.2/PHP 8.5.10/SQLite: 70 tests, 10,303 assertions pass, including 35 focused tests/173 assertions. Native REST-dispatch processes prove identical create retries produce one record, distinct-key slug collisions and same-version edit races reject the loser, hook/receipt failures roll back and allow same-key retry, and cron/AJAX saves preserve raw query bytes. Source-aware review prompted fixes for nested savepoints, ghost caches, stale reads and capacity snapshots.

The hosted MySQL 8 matrix runs WordPress 6.4 and 7.1 unit tests plus native multi-process race/failure probes and an actual REPEATABLE READ caller snapshot versus an independent core writer. See exact PR-head checks for terminal results. Proof scripts refuse databases other than wordpress_tests/wptests_, use existing fixture actors, and block outbound HTTP. This is REST-dispatch/CLI evidence, not a real HTTP Location header, browser UI or source-blind independent validation.

#53 owns CSV preview, job persistence/cancellation and 10,000-row resource proof. #116 owns final upgrade/package/public HTTP redirect evidence. #74 visual approval remains pending; no UI implementation is included. Do not mark the 1.2 train complete.

Rollback: revert the focused command commits; no schema migration or version bump. Existing CPT records remain. Old code ignores the non-autoloaded command coordination/receipt options; preserve receipts if command retry support may be restored.
