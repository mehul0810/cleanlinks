# CleanLinks 1.2.0 local candidate

This candidate implements #115 management workspace, #51 single create, #50 edit/selection, #53 private paste/CSV import, and #55/#56 responsive/accessibility behavior on the merged #114 foundation. #116 validation concerns the exact local ZIP. It is not a release, deployment, merge or owner visual-approval receipt.

Existing CPT IDs, slugs, destinations, statuses, metadata, counts, groups, URLs and extension hooks remain. No custom table or link-data migration is introduced. New coordination, receipt and private staging options are non-autoloaded. Reversible selection uses WordPress trash/restore; no permanent deletion action is added.

Qualification fixes preserve the WordPress 5.5 minimum: the transaction-state probe recognizes only mysqli error 1305 with the exact missing internal savepoint message, restores wpdb suppression in finally, and leaves global mysqli reporting unchanged. Other SQL exceptions still fail closed. Empty/single-page pagination normalizes the null return to a string before HTML sanitization. Real strict-driver ownership/rollback proof and unexpected-exception/rendering regressions cover these boundaries.

Five newer #112 maintenance changes were reconciled selectively: apostrophe-safe public redirects, post-core-sanitization query fidelity, copy-feedback recovery and corresponding regressions. The old metabox implementation was not copied over the merged shared service.

The authoritative package command is `npm run package:release -- /absolute/path/cleanlinks-1.2.0-local-candidate.zip`. It builds assets, reconstructs production-only Composer dependencies and validates the exact ZIP. The validator rejects missing command/workspace/import assets and development docs/tests. Runtime metadata is synchronized at 1.2.0; this does not publish or designate a production release.

See `../../DESIGN.md` for the source-backed review packet, role/state behavior and compatibility limits. The bounded import transaction commits valid rows despite ordinary peer validation failures, but a staging journal/commit failure rolls back the whole current batch. Prior committed batches remain. Nested commands preserve caller transactions. Outer rollback clears touched post/meta/object-term/group caches as well as staging cache entries.

Final evidence and checksums are recorded outside the package in `deliverables/120-final-report.md` relative to the workspace root. Do not ship design/proof material, temporary authentication, test databases or development dependencies. Do not claim universal persistent-cache support, arbitrary third-party transactional safety, complete WCAG compliance, PHP 8.1/8.2/8.3 runtime qualification for the new UI, or a production-ready deployment until the owner/release process accepts those boundaries.
