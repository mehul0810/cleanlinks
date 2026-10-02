# CleanLinks 1.2 management design contract

Status: proposed; visual and information-architecture approval pending (#74).
Baseline: candidate/1.1.2-integrated, 4275febd509a3944d86f5bd3fa54678724b3bda7. Historical develop prototypes are not the implementation baseline.

## Product direction

Help WordPress users create, inspect and manage short links with destination-first forms and dependable saved-state feedback. Keep WordPress admin chrome and its menu. Recommend All Links as the landing surface, with Add Link and Bulk Create as primary task entries; Groups and existing Export remain dedicated WordPress admin surfaces. There is no new analytics dashboard, settings area or custom sidebar.

Prefer WordPress system typography, native control sizes, restrained blue primary actions, gray page background and white working surfaces. One primary action per state. Use normal tables on desktop and labeled stacked rows at narrow widths. A saved short URL is a result, while a proposed URL is explicitly a preview.

## Source and control map

| Control or information | Current source / command boundary |
| --- | --- |
| Title, ID, slug, status, author | cleanlinks CPT via PostTypeRegistrar; future #114 commands |
| Saved short URL / Copy | WordPress get_permalink; never an unsaved slug |
| Destination | cleanlink_redirect_url; UrlValidator and LinkMetadataCommand |
| Nofollow | cleanlink_redirect_nofollow; existing X-Robots-Tag behavior |
| Lifetime clicks | cleanlink_redirect_count; AccessCounter |
| Redirect | Existing Redirector; fixed default behavior, no new selector |
| Group membership | cleanlinks_groups taxonomy; TaxoNomy |
| Export | Admin/Export, ExportQuery and ExportCsvSerializer; published links only, manage_options |
| Bulk preview / results | New #53 + #114 contracts; absent from current candidate |

Do not show time series, recent clicks, health, unique clicks, 30-day totals, UTM builders or analytics export. Historical concept requests for these surfaces are superseded by the updated issues.

## Screen and state specification

| Surface | Desktop composition | Narrow composition | Required states |
| --- | --- | --- | --- |
| All Links (#115) | Heading + Add Link; secondary Bulk Create; search/status/group filters; table with title, saved URL, destination, status, groups and lifetime clicks; pagination | Search first, filters wrap; labeled list rows; row actions remain reachable | Empty, filtered empty, loading, permission failure, copy success/failure |
| Single create (#51) | Destination full-width first; editable suggested title and slug next; proposed short URL labeled Preview; existing groups; advanced nofollow disclosure; Create link and Save draft according to role | One column, input labels above, action row wraps; results stay in reading order | Invalid field, expired session with input retained, slug conflict, submitting, confirmed saved ID/URL, Create another |
| Edit (#50) | Saved URL/copy, destination form, status/groups, lifetime clicks and fixed redirect semantics together; Save changes; explicit status/trash actions | Saved summary then form then actions; no fixed obstruction | Unchanged, dirty, rejected save, stale edit conflict, unpublish retains count, restore, explicit slug-change confirmation |
| Bulk input (#53) | Paste URLs or Upload CSV; supported headers/template/limits and duplicate policy before input | One column with source choice then input | Encoding/size/type/header error; input retained |
| Mapping and preview (#53) | Map destination/title/slug/groups; zero-write preview with row number, proposed values, ready/error/duplicate reason; select valid rows; publication choice | Row summary with expandable details and selectable controls | Invalid rows mixed with valid, duplicate slug defaults to skip, correction/removal, concurrent conflict at commit |
| Import and results (#53) | Progress with exact persisted counts; cancel stops remaining rows; final saved IDs/URLs, skipped/failed reasons, error CSV and retry failed only | Status summary and labeled row results | Interrupted/resumed, retry without duplicate creation, cancelled with already-created rows retained, partial failure |
| Groups | Native taxonomy surface retained, existing membership semantics | Core WordPress responsive behavior | Empty, duplicate, validation, permission denied |
| Export | Existing export action with precise published-only scope and actual schema; no analytics choice | One-column scope/action | Permission denied, download start and failure; no fabricated completion |

## Permissions, feedback and accessibility

Use current CPT post capabilities and object-level edit_post checks. Publish controls require publish_posts. Group assignment checks taxonomy assign_terms; group administration checks manage_categories. Export retains manage_options. Hiding controls is only presentation: server authorization is authoritative.

Show field errors next to their controls and link them from a focusable error summary. Preserve typed values after validation or session failure. Announce persisted success, copying, progress and failures through appropriate live regions. Label every input; use semantic buttons, tables and headings. Status must be readable without color. Maintain visible keyboard focus and logical order. Unsaved navigation offers stay/leave; saved URL changes require explicit confirmation explaining that shared links can break.

Desktop review target: 1440 x 1024. Narrow review target: 390 x 844. Subsequent #55/#56 proof must include 320px reflow, 200% zoom, long destination/slug/group names, empty/error/busy/success states, keyboard-only use, focus restoration and screen-reader feedback.

## Reference receipt and approval gate

Inspected existing admin screenshot /private/tmp/cleanlinks-ui-desktop-proof.png (1280 x 800). It shows the legacy CPT edit surface, short URL/copy, destination, nofollow, lifetime clicks, Publish and Groups. This is existing-state evidence, not a newly generated 1.2 mockup or final-head UI proof.

The four historical clipboard concepts linked in #74 are absent at their recorded paths. No persistent Product Design context exists. Approval to continue visual ideation from the current screenshot/source has been requested; it has not been inferred. Next deliverable: three independent WordPress-native visual directions for the representative creation flow, then the selected desktop/narrow screen packet. No production UI, navigation, copy or CSS implementation until owner approval.

This contract starts #74; it does not complete its mockup or approval acceptance criteria. Backend #114 may continue independently. Final upgrade/redirect/package proof remains #116, including actual HTTP Location evidence rather than redirect-hook-only assertions.
