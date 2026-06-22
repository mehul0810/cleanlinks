# CleanLinks Release Guide

CleanLinks is a WordPress.org plugin published under the `cleanlinks` slug. Keep release work conservative and source-of-truth driven.

## Current Release Model

- Production releases are GitHub releases/tags that trigger the WordPress.org deploy workflow.
- `main` is production-only. Do not target `main` for routine product, docs, tooling, or release-prep PRs.
- Use `develop` for unmilestoned development integration and prep work that is not yet assigned to a release train.
- Use `release/<release-version>` for milestone stabilization once the active release train exists, for example `release/1.1.0`.
- Latest known production line at the time this guide was added: `1.0.3`.
- Prerelease GitHub releases build a ZIP artifact but do not publish to WordPress.org.
- Stable GitHub releases trigger `.github/workflows/deploy.yml`, which uses `10up/action-wordpress-plugin-deploy` with `SLUG=cleanlinks`.

Always verify the latest GitHub releases, tags, open PRs, milestones, and workflow status before release work.

## Branch and PR Base Rules

CleanLinks uses a production-protected branch model:

- `main` is production-only and should receive only owner-approved production release or hotfix integration.
- `develop` is the default base for unmilestoned, non-production work such as docs, tooling, product-design prep, WordPress.org presentation prep, and reversible backlog work.
- Milestone-assigned work targets `release/<release-version>`, where `<release-version>` comes from the active release train name such as `1.1.0`. Do not use the GitHub milestone ID as the release branch name.
- Create the release branch from the verified current integration base when the active release train is opened.
- If a PR is opened against the wrong base, preserve the work by retargeting or replaying it onto the correct non-production base; do not merge the wrong-base PR into `main`.
- Production or beta release actions still require explicit current owner approval: release tags, GitHub releases, prereleases, WordPress.org deploys, or final release approval.

Until the next post-`1.0.3` milestone and due date are created, keep #50-#57 and similar prep work unmilestoned or on `develop` unless a release branch already exists for that exact release train.

## Version Prep Checklist

Before preparing a release PR or release tag, verify and update as needed:

- `cleanlinks.php` plugin header version.
- `readme.txt` stable tag and changelog.
- `README.md` public metadata when it mirrors WordPress.org content.
- `package.json` and `composer.json` versions when package metadata is part of the release.
- `.wordpress-org/blueprints/blueprint.json` if the release changes Playground behavior.
- Generated assets and translation files when source changes require them.

## Validation Checklist

Use the smallest reliable validation for the changed boundary, then broaden for release candidates:

- `git diff --check`
- `php -l` for touched PHP files
- `composer lint`
- `composer check-cs`
- `composer test`
- `npm run build` when assets or release packaging are affected
- `composer lint-blueprint` when Playground metadata changes

If a validation command fails because of pre-existing unrelated debt, document the exact failure and keep the PR scoped.

## WordPress.org Presentation Prep

Use this checklist for prep-only WordPress.org page work such as issue #57. It is documentation and asset readiness work until the owner separately approves a release train, tag, GitHub release, and WordPress.org deploy.

Current public state verified on 2026-06-22:

- WordPress.org API reports CleanLinks `1.0.3`, requires WordPress `5.5`, tested up to `6.8.5`, requires PHP `8.1`, active installs `0`, and download link `cleanlinks.1.0.3.zip`.
- Public support state is zero support threads, zero resolved threads, zero ratings, and zero reviews.
- Public WordPress.org metadata exposes no screenshots, banners, or icons through the plugin information API.
- GitHub latest stable release is `1.0.3`, published 2025-06-27, and is not a draft or prerelease.
- `readme.txt` keeps `Stable tag: 1.0.3`; do not change it for presentation prep unless the owner confirms the next release train and the version metadata changes in the same release-prep scope.

Supported WordPress.org asset filenames and dimensions to validate before a publishing release:

- Banners: `banner-772x250.(jpg|png)` and optional high-DPI `banner-1544x500.(jpg|png)`. The high-DPI banner is an add-on and must not be the only banner.
- Icons: `icon-128x128.(png|jpg|gif)`, optional high-DPI `icon-256x256.(png|jpg|gif)`, or `icon.svg`.
- Screenshots: `screenshot-1.(png|jpg)`, `screenshot-2.(png|jpg)`, and so on. Screenshot captions in `readme.txt` must match the committed screenshot files.
- Localized variants may use locale suffixes such as `-rtl`, `-es`, or `-es_ES` when a localized asset is intentionally prepared.

Prep and validation steps:

- Keep WordPress.org page copy to current released functionality unless the owner confirms the target release line for unreleased work.
- Keep the short description within WordPress.org limits and use no more than five competitor-neutral tags.
- Review `.distignore` before packaging so source-only docs, tests, development tooling, and `.wordpress-org` metadata are not bundled into the runtime ZIP unless intentionally required.
- Validate `readme.txt` with the official WordPress.org readme validator or an equivalent documented checklist before release.
- Validate asset filenames and image dimensions before any stable GitHub release that will deploy to WordPress.org.

Non-goals unless explicitly approved by the owner:

- Do not publish to WordPress.org.
- Do not create or push release tags.
- Do not create draft, prerelease, or stable GitHub releases.
- Do not close issues, retarget milestones, or change due dates.
- Do not advertise future `1.1.0` functionality, pro functionality, pricing, licensing, privacy changes, or support promises as shipped.

## GitHub Release Behavior

- Draft or prerelease GitHub releases must not be treated as production.
- A prerelease runs `.github/workflows/prerelease.yml` and uploads `cleanlinks.zip` only.
- A stable GitHub release runs `.github/workflows/deploy.yml`, deploys to WordPress.org SVN, and uploads `cleanlinks.zip`.
- Do not create tags, prereleases, stable releases, or WordPress.org deploys without explicit current owner approval.

## Milestones

- Milestones should represent active release trains and have due dates.
- The open `1.0.0` milestone is stale launch-planning state: it has no open issues and predates the current `1.0.3` production line.
- Do not assign new work to `1.0.0` unless the owner explicitly reopens that release train.
- Before closing, renaming, or superseding the old milestone, confirm the intended next release line and due date policy with the owner.

## Stale PR Handling

Older draft PRs should be reviewed against current `main`, current issues, and current product direction before use.

- Do not merge stale drafts as-is.
- If still relevant, convert the scope into a current issue-backed branch/PR with fresh validation.
- If obsolete, ask for owner approval before closing the PR.
