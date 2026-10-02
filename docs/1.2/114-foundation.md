# Shared link command contract — issue #114

Baseline: candidate/1.1.2-integrated at 4275febd509a3944d86f5bd3fa54678724b3bda7. Production UI/design work remains gated by #74.

## Application contract

LinkCommands accepts explicit unslashed create/update arrays. Supported fields: destination, title, slug, status, existing integer group IDs, boolean nofollow, request_key, and (updates) integer id + expected_version. confirm_slug_change=true is required to intentionally change an existing slug. Arbitrary post/meta fields, term creation, malformed lists/objects/scalars, forged IDs and unsupported statuses are rejected before mutation. Current supported statuses are draft/pending/publish/private; trash/restore remain a later #50 slice.

Every command requires an actor-scoped 16–64-character request key. Reusing an identical command returns its saved receipt without another link/save; changing input with that key rejects with request_key_conflict. Receipts are durable, non-autoloaded options committed in the same transaction as the link. They have no automatic expiry, because removing a receipt could make a delayed retry create a duplicate. No new credential or custom table is introduced.

read(id) returns the actual ID/title/slug/status/destination/nofollow/groups/public URL plus a version hash. Updates require this token; stale snapshots reject with stale_version. Counts are excluded from the token so clicks do not prevent editing. Duplicate explicit or derived slugs reject with slug_conflict rather than overwriting or silently renaming another record; draft slugs are checked too. Callers can propose a new slug and resubmit with a new key. Successful results always reflect persisted identity/state.

execute_rows accepts 1–50 row commands with independent stable keys. Each result has a 1-based row plus saved data or an error code and field/status. Valid rows can commit despite invalid peers; resubmitting returns their receipts. This is the bounded command primitive, not a CSV parser, preview UI, background import job or transactional bulk undo (#53).

## Persistence and compatibility

Keep the cleanlinks CPT and cleanlink_redirect_url/cleanlink_redirect_nofollow metadata. Lifetime counts, groups, existing IDs, public routing, extension filters and export schema remain. Destination safety uses the current UrlValidator without HTML output escaping; raw query separators, repeated parameters, percent encoding and plus signs survive storage. Core persists only owned metadata/terms before save_post hooks; command saves skip the legacy request-global adapter. The nonce-protected legacy adapter remains, and direct metadata saves use rollback-aware persistence.

A database transaction and permanent non-autoloaded mutex option serialize application/legacy metadata writers. The transaction probes actual savepoint state, preserves caller-owned transactions, locks the update post and collision query, checks readback, and rolls back partial writes. Post/meta/object-term/term caches are invalidated after rollback. MySQL requires InnoDB for mutable tables; SQLite integration must support the tested transaction/savepoint semantics. Unsupported storage fails closed with storage_unavailable; there is no silent non-atomic fallback. A caller-owned transaction must be committed by that caller before its result is presented as durable.

Standard WordPress persistence hooks still fire. Database rollback cannot undo external effects performed by third-party hooks, nor control arbitrary direct SQL writers that bypass the service. Extension hooks that commit/DDL inside a save are unsupported. Cross-process mutex proof covers service writers; no blanket claim is made about arbitrary legacy/third-party creation paths. Nontransactional installations and externally committing hooks need a separate compatibility decision before rollout.

## Access and transport

CPT capabilities come from the registered post type: create_posts; object-level edit_post for own/others; publish_posts for publish/private. Existing group assignment uses the taxonomy assign_terms capability (default edit_posts); group administration stays manage_categories. Existing export stays manage_options, published-only. No access is widened.

REST routes: POST /cleanlinks/v1/links, GET/PATCH/PUT /cleanlinks/v1/links/{id}, POST /cleanlinks/v1/link-commands with a rows envelope. Each permission callback requires an authenticated current user and an X-WP-Nonce for wp_rest, plus create/object-edit permission; the application rechecks publication/group/object authority. Identity belongs to the route; an id in a single-save JSON body is rejected. No arbitrary post/meta mutation endpoint, destination fetch, production UI or navigation is added. Trusted in-process/cron callers set an explicit WordPress actor and call the application; request globals and DOING_AJAX/DOING_CRON do not control its persistence.

## Evidence and remaining proof

Disposable WordPress 7.1.2/PHP 8.5.10/SQLite: 59 tests, 10,255 assertions pass, including 24 focused tests/125 assertions. Lint checks 46 PHP files; coding standards check 31 files. Separate native REST-dispatch processes prove identical create retries yield one published record, distinct keys for the same slug return one success + slug_conflict, and concurrent same-version edits yield one success + stale_version with readback of the winner. This is REST-dispatch/CLI evidence, not a real HTTP Location header, browser UI or source-blind independent validation.

Before #114 completion: exact-head hosted MySQL CI after the expanded commit; MySQL multi-process race proof and a source-aware review of locking/transaction/receipt behavior; role coverage for overridden/custom group capabilities and real session expiry; native top-level failed-write/exception/receipt-write recovery beyond the existing integration failure injections; durable-receipt uninstall cleanup/retention review; agreement on supported storage/extension boundaries. None depends on a visual decision. #53 owns CSV preview, job persistence/cancellation and 10,000-row resource proof. #116 owns final upgrade/package/public HTTP redirect evidence. Do not close #114 or mark the 1.2 train complete.

Rollback: revert the focused command commits; no schema migration or version bump. Existing CPT records remain. Old code ignores the non-autoloaded command coordination/receipt options; preserve receipts if command retry support may be restored.
