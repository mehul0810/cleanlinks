# CleanLinks Release Guide

CleanLinks is a WordPress.org plugin published under the `cleanlinks` slug. Keep release work conservative and source-of-truth driven.

## Current Release Model

- Production releases are GitHub releases/tags that trigger the WordPress.org deploy workflow.
- The current default branch is `main`.
- Latest known production line at the time this guide was added: `1.0.3`.
- Prerelease GitHub releases build a ZIP artifact but do not publish to WordPress.org.
- Stable GitHub releases trigger `.github/workflows/deploy.yml`, which uses `10up/action-wordpress-plugin-deploy` with `SLUG=cleanlinks`.

Always verify the latest GitHub releases, tags, open PRs, milestones, and workflow status before release work.

## Version Prep Checklist

Before preparing a release PR or release tag, verify and update as needed:

- `cleanlinks.php` plugin header version.
- `readme.txt` stable tag and changelog.
- `README.md` public metadata when it mirrors WordPress.org content.
- `package.json` and `composer.json` versions when package metadata is part of the release.
- `.wordpress-org/blueprints/blueprint.json` if the release changes Playground behavior.
- `.wordpress-org/` icon and banner PNGs only through the CleanLinks PO/release path when owner-supplied WordPress.org artwork changes.
- Generated assets and translation files when source changes require them.

## Validation Checklist

Use the smallest reliable validation for the changed boundary, then broaden for release candidates:

- `git diff --check`
- `php -l` for touched PHP files
- `composer lint`
- `composer check-cs`
- `composer test`
- `npm run build` when assets or release packaging are affected
- `npm run lint:pkg-json`
- `npm run package:release -- /tmp/cleanlinks-release.zip` when validating a release ZIP locally
- `npm run validate:package -- /tmp/cleanlinks-release.zip` when revalidating an existing ZIP
- `composer lint-blueprint` when Playground metadata changes

If a validation command fails because of pre-existing unrelated debt, document the exact failure and keep the PR scoped.

## Local Release Package

Use `npm run package:release -- /tmp/cleanlinks-release.zip` as the authoritative local package command. The `npm run plugin-zip` command is an alias for the same builder. It mirrors the prerelease workflow by building npm assets, copying the release payload through `.distignore`, installing production-only Composer dependencies into the clean staging directory, and writing a validated ZIP. The builder never copies the developer's existing `vendor/` directory.

The generated ZIP must include runtime plugin files such as `cleanlinks.php`, `config/`, `src/`, `vendor/autoload.php`, `dist/`, `languages/`, `uninstall.php`, `readme.txt`, and the `.wordpress-org/` release assets. It must exclude development-only files such as `.git/`, `.github/`, `node_modules/`, `tests/`, source assets, Composer development packages, package manifests, CI/config files, source ZIPs, and nested release output. Package generation runs `scripts/validate-release-package.php` against the exact ZIP and fails when required files are missing or forbidden content is present.

The repository package-json lint configuration extends the WordPress preset but requires `GPL-3.0-or-later` for this plugin. This resolves the preset's GPL-2.0-only valid-value rule without changing or weakening the GPLv3-or-later terms in plugin metadata.

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
