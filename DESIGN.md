# CleanLinks Design Contract

Use this document as the product design source of truth for CleanLinks admin UI, public plugin presentation, and website/docs surfaces. Keep it concise and implementation-facing. Detailed feature work belongs in focused issues and PRs.

## Product Design Principles

- Feel native to WordPress admin first. Prefer familiar list tables, metaboxes, settings sections, notices, and action links over custom app patterns unless the workflow clearly needs more structure.
- Make link state visible at a glance. Users should quickly understand the destination URL, short URL, redirect behavior, rel policy, health status, and recent activity.
- Optimize for repeated operations. Creating, checking, copying, exporting, and reviewing links should require minimal movement across screens.
- Keep advanced controls progressive. Show essential fields first, then reveal analytics, health, import/export, and policy details where they help the current task.
- Avoid promising unreleased behavior. Public copy, screenshots, and docs must match the owner-approved release train.

## Admin UI Patterns

### Add/Edit Command Center

The add/edit screen should give each CleanLink a compact command center that supports issues #50 and #51:

- Show the short URL, destination URL, redirect type, rel policy, health status, and useful click summary without forcing users to scan multiple boxes.
- Provide clear actions for copy short URL, open short URL, run health check, and view analytics.
- Disable or explain actions that cannot run yet, such as opening an unsaved draft link.
- Keep primary editing fields close to their status and actions so users can review cause and effect before publishing.
- Do not replace WordPress publishing controls; complement them.

### Lists, Tables, And Mobile Cards

CleanLinks list screens should support scanning and bulk review, especially for issue #55:

- Desktop list tables should prioritize short URL, destination, status, group, health, clicks, and last meaningful activity.
- Row actions should be predictable: edit, copy, open, health check, analytics, export-related actions when relevant.
- Mobile layouts may become card-like, but each card must preserve the same core information hierarchy as the desktop table.
- Avoid horizontal overflow when a field can wrap or collapse safely.
- Long URLs should truncate visually while keeping the full value available through copy, title text, or a detail view.

### Dashboard And Action Queue

The dashboard should help users decide what to do next, aligned with issue #52:

- Surface broken or unchecked links, unusual click changes, stale links, and import/export follow-ups before decorative metrics.
- Distinguish informational metrics from actions that need user attention.
- Make empty states useful by offering the next safe action, such as creating a first link or importing links.
- Avoid making the dashboard a marketing page inside wp-admin.

### Settings

Settings work should align with issue #54:

- Make save state obvious with clear success, error, and unchanged-state feedback.
- Group settings by user intent: redirects, tracking, rel behavior, import/export, health checks, and cleanup.
- Explain risky settings before the control, not after the failure.
- Do not hide global defaults that affect new links.

### Import And Export

Import/export work should align with issue #53:

- Separate preview, mapping, validation, execution, and result states.
- Show row counts, skipped rows, validation errors, and download/retry actions clearly.
- Treat destructive or irreversible operations as confirmation moments.
- Keep exports predictable: users should know what filters, columns, and date ranges are included.

### Analytics And Health

- Keep analytics summaries readable for non-technical users.
- Show enough context to explain the number: timeframe, total clicks, recent clicks, and whether tracking is enabled.
- Health checks should show status, HTTP code when available, last checked time, and a retry action.
- Errors should state what CleanLinks observed and what the user can do next.

## State Patterns

- Empty states: state what is missing and offer one direct next action.
- Loading states: preserve layout and name the operation when it may take more than a moment.
- Success states: confirm the completed action and show the affected link or file.
- Error states: explain the failed operation, preserve user input, and provide a recovery path.
- Saved states: distinguish "saved", "saving", "not saved", and "no changes".
- Partial states: show which rows, links, or checks succeeded and which require attention.

## Accessibility And Responsive Behavior

Accessibility work should support issue #56 and all future UI changes:

- Every interactive control must be keyboard reachable and have a visible focus state.
- Icon-only actions need accessible names and clear hover/focus labels when the meaning is not obvious.
- Status changes from copy, save, import, export, and health checks should be announced through WordPress admin notice patterns or equivalent accessible messaging.
- Do not rely on color alone for health, error, or success state.
- Maintain readable contrast in admin tables, cards, notices, badges, and disabled controls.
- Respect reduced-motion preferences for any loading or transition effects.
- Responsive layouts must keep text, controls, and row actions readable at common mobile admin widths.

## WordPress.org And Website Presentation

Public presentation work should support issue #57:

- `readme.txt` should describe only the features in the owner-approved release line.
- Tags must stay limited, accurate, and competitor-neutral.
- Screenshots should show real product surfaces: link list, add/edit workflow, settings, analytics or health, and import/export when released.
- Screenshot captions must match the actual files and current UI.
- Banners and icons should make CleanLinks recognizable without implying a feature set that has not shipped.
- Deeper setup, troubleshooting, and workflow guidance should live on the product website or docs, with the plugin readme linking out when appropriate.

## Copy And Tone

- Use direct, calm admin copy. Prefer "Run health check" over vague wording like "Analyze".
- Name the object being changed: link, short URL, destination, import file, export file, settings.
- Avoid blame in errors. State what happened and how to recover.
- Keep public copy specific to CleanLinks value: branded short links, redirect management, click tracking, import/export, health, and WordPress-native operation.
- Do not use competitor comparisons or competitor names in public repo docs, readme copy, or WordPress.org assets.

## Visual Non-goals

- Do not turn wp-admin screens into a marketing landing page.
- Do not introduce a heavy custom design system for a small plugin admin.
- Do not add decorative UI that reduces scan speed or accessibility.
- Do not redesign all screens in one PR.
- Do not add pricing, licensing, privacy, schema, or public API commitments from design docs.

## Issue Map

- #50: Add/edit command center.
- #51: Progressive quick create workflow.
- #52: Dashboard action queue.
- #53: Enterprise-ready import/export.
- #54: Settings confidence and save behavior.
- #55: Mobile table and card layouts.
- #56: Accessibility QA.
- #57: WordPress.org presentation assets.

This contract guides those issues but does not replace their acceptance criteria.
