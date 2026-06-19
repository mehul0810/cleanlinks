# CleanLinks Agent Guide

Use this file as the repo-local source of truth for Codex and other agentic maintenance work on CleanLinks.

## Source Of Truth

- GitHub releases/tags define the current production line. As of the first version of this guide, production is `1.0.3` on `main`.
- WordPress.org slug: `cleanlinks`.
- Default branch: `main`.
- Local development site may be `development.wp.local`, but branch, issue, release, and milestone decisions must be verified from GitHub and this repo before changes.
- Treat older long-lived and Copilot branches as historical until their issue/PR scope is revalidated.

## Work Intake

- Start with `git status --short --branch`, current GitHub issues, open PRs, milestones, releases/tags, and this file.
- Use GitHub issue-first intake for new work unless the owner explicitly says not to.
- Search open issues, open PRs, recent closed issues, release notes, and repo docs before creating issues or PRs.
- Keep one primary issue per implementation PR by default.
- Preserve user work. If the local development checkout is dirty, use a fresh isolated checkout or stop before editing.

## Branch And PR Rules

- Use `main` as the base unless a current repo doc or owner decision creates a `develop`, `release/*`, or `hotfix/*` line.
- Do not rely on GitHub's default PR base without checking the intended milestone/release line.
- PRs must state base branch evidence, linked issue, scope, non-goals, validation, release impact, and rollback notes.
- Do not merge PRs, enable auto-merge, close issues, delete branches, publish releases, or deploy to WordPress.org without explicit current owner approval.

## Validation

- PHP syntax for touched PHP files: `php -l path/to/file.php`.
- PHP lint: `composer lint`.
- PHP coding standards: `composer check-cs`.
- PHPUnit: `composer test`.
- JavaScript/CSS/build changes should use the repo `package.json` scripts, including `npm run build` when assets or release packages are affected.
- Docs-only changes should at least pass `git diff --check` and link/path review.

## Release Discipline

- Read `RELEASE.md` before version, tag, prerelease, stable release, package, or WordPress.org deploy work.
- Do not create a prerelease for a future milestone until the previous milestone has a production release.
- Milestones need current due dates. If a milestone is stale, empty, or ambiguous, raise or document the decision instead of silently reusing it.
- The open `1.0.0` milestone is historical launch planning until the owner confirms whether to close, rename, or supersede it.
