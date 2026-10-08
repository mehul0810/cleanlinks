# CleanLinks 1.2 management design

Proposed local candidate for review; no production publication or visual approval is claimed. This reconciles #74 with the implemented #115/#51/#50/#53 flows while preserving the existing `cleanlinks` post type. Baseline is release/1.2.0 at 741dc638, descended from the owner-selected 4275febd #112 integration. Historical clipboard images were unavailable; current source, existing brand and native WordPress controls supply the review target.

The desktop/narrow review packet is `../deliverables/120-design/index.html`, with original full-page screenshots at 1440 and 390 CSS pixels. It includes All Links, create/edit, CSV mapping, zero-write preview, persisted results, existing Groups and published-only Export. These are rendered candidate screens with disposable data, not imagined analytics or an approval receipt.

Keep WordPress admin chrome, system typography, normal buttons/forms/tables, and existing Groups/Export semantics. Use a restrained teal header (#eaf5f2; ink #153c3a; navigation accent #076f63) and blue WordPress primary actions. All Links is the landing page. Add link and Bulk add are task entries. Narrow tables become labeled cards; controls remain in reading order. No new analytics dashboard, time series, health feed, UTM builder or advanced settings is promised.

## Controls and data

| Surface/control | Actual source or boundary |
| --- | --- |
| Title, ID, slug, author, publication | Existing cleanlinks WordPress posts |
| Saved URL and Copy URL | get_permalink; copy failure offers manual recovery |
| Destination | cleanlink_redirect_url; current WordPress URL validation, raw query bytes preserved |
| Nofollow | cleanlink_redirect_nofollow; existing redirect header semantics |
| Lifetime clicks | cleanlink_redirect_count; preserved when editing/unpublishing/restoring |
| Groups | Existing cleanlinks_groups terms; no term creation through a link command |
| Single save | LinkCommands, actor-scoped receipts, stale-version token, explicit slug-change confirmation |
| Bulk staging | Actor-private non-autoloaded ImportJobs options, 24-hour access expiry |
| Bulk execution | At most 50 selected valid rows per serialized transaction; committed prior batches retained |
| Export | Existing ID/Title/Redirect From/Redirect To CSV; published-only, manage_options |

## States and permissions

Create/edit is destination-first. New links offer editable title/slug suggestions and a core-routing URL preview labeled as unconfirmed until saved. Forms default to Draft, retains rejected normal input, marks the returned invalid field and associates it with the focused error summary. Saved URLs and counts appear only from persisted records. A stale editor must explicitly reload current values. Changing a saved slug requires confirmation because shared URLs can break. Keyboard input triggers a native unsaved-navigation warning; submitting clears that warning.

List filters and return navigation retain search, status, group and page. Selected trash/restore requires confirmation and rechecks every record's delete capability; unrelated post types remain untouched. Failed IDs are reported for recovery. Restoration follows WordPress status behavior and may return as Draft; counts/groups stay.

Paste/CSV supports UTF-8, quoted commas/newlines and explicit column mapping. Preview creates no links. Rows show editable destination/title/slug/status/groups/nofollow, selection and exact validation/duplicate reasons. Save corrections and selection on each preview page before starting. Duplicate destinations are permitted under different slugs; duplicate/existing slugs are skipped, never overwritten. Imports can pause/cancel/resume and retry failed rows with original keys. Automatic execution is optional; ordinary server-rendered batch forms work without JavaScript. Error CSV uses the existing formula-neutralizing serializer.

Publication uses publish_posts; creation uses edit_posts; saved records use object-level edit_post. Group assignment uses taxonomy capabilities. Groups administration retains manage_categories, Export retains manage_options. The server rechecks capability/nonce/input on every request. Existing Classic editor remains explicitly available.

## Compatibility and limits

Workspace saves/imports require transactional mutable tables (InnoDB on MySQL) and the qualified native request-local WordPress cache. Persistent/custom object caches fail closed. Classic saves retain ordinary WordPress behavior without atomic rollback. Third-party hooks that commit SQL/DDL or perform external effects are outside rollback guarantees; arbitrary bypass writers are not serialized. Caller-owned transactions must be committed by their owner before announcing durability.

Imports accept at most 10,000 rows / 5 MiB, 5 private jobs per actor and 100 per site. Access expires after 24 hours; cleanup requires the supported transactional/native-cache configuration. Switching to an unsupported configuration can defer physical cleanup; uninstall removes the owned staging/receipt footprint across sites without deleting links or settings.

Automated browser/axe evidence is in `../deliverables/120-a11y-evidence.json`; functional, keyboard, no-JavaScript and 320/390/768/1280 reflow proof is in `../deliverables/120-browser-evidence.json`. Automated scans do not establish complete WCAG conformance. Source-aware independent review and actual process-race evidence are separate from owner design approval.

## Owner-requested layout revision

The owner reviewed the previews and requested better viewport use, clearer structure and a plugin header. This feedback authorizes the revision but is not final visual approval. The header now uses the existing WordPress.org CleanLinks icon and readable product name, with compact task navigation and an active-page indication. All Links retains prominent Add link/Bulk add actions. Create/edit uses the full admin content width: destination/title/slug on the left, existing publishing/group/nofollow controls on the right, and a consistent bottom action row. At the native WordPress 782px breakpoint the same DOM order becomes a single column. The promotional hero, fixed 760px editor limit and redundant form-page header actions were removed. Filters use the available list width. No additional product features or navigation tabs were introduced.

Actual revised captures are in the existing review gallery; exact source/package identity and bounded regression evidence are in `../deliverables/120-layout-report.md`. Automated checks and independent review do not substitute for owner approval or the outstanding manual screen-reader validation.
