# WordPress.org Presentation Package

Issue: #57 - Prepare WordPress.org presentation assets for the next CleanLinks release

## Current Source Snapshot

Verified for this prep package on 2026-06-26:

- Latest GitHub production release: `1.0.3`.
- Working base: `origin/develop` at `e6c3b73`.
- Open PRs at verification time: none.
- Public WordPress.org API: version `1.0.3`, tested up to `6.8.5`, no screenshots exposed, no banners exposed, no support topics.
- `develop` readme metadata: `Tested up to: 7.0`, `Stable tag: 1.0.3`.
- Prepared repository assets already present on `develop`:
  - `.wordpress-org/banner-1544x500.png` - 1544 x 500
  - `.wordpress-org/banner-772x250.png` - 772 x 250
  - `.wordpress-org/icon-128x128.png` - 128 x 128
  - `.wordpress-org/icon-256x256.png` - 256 x 256

## Proposed Release Scope

Recommended next release train: `1.0.4` maintenance/presentation release.

Scope:

- Publish the existing WordPress 7.0 compatibility metadata already prepared on `develop`.
- Include the existing WordPress.org banner and icon assets.
- Add the screenshot files and matching captions listed below.
- Run final readme and package validation before requesting owner approval for a production release or WordPress.org deploy.

Non-goals:

- No production tag, GitHub release, beta release, or WordPress.org publish in this prep package.
- No milestone due-date changes.
- No feature claims beyond current CleanLinks functionality already represented in the code/readme.
- No 1.1.0 positioning unless the owner separately confirms a feature release train.

## Screenshot Package

Capture real wp-admin screens from the target release package. Use neutral sample data, avoid real affiliate IDs or private URLs, and keep screenshots focused on the product UI.

| File | Capture | Caption |
| --- | --- | --- |
| `.wordpress-org/screenshot-1.png` | CleanLinks list table with sample links, `Redirect To`, and `Total Clicks` columns visible. | Manage branded short links from the CleanLinks list, including target URLs and click totals. |
| `.wordpress-org/screenshot-2.png` | Edit Link screen showing the Redirection Settings panel and destination URL field. | Set the destination URL for each branded link from the Redirection Settings panel. |
| `.wordpress-org/screenshot-3.png` | Import/Export screen showing supported import flow and CSV export action. | Import supported links or export your CleanLinks data from the Import/Export screen. |
| `.wordpress-org/screenshot-4.png` | Groups taxonomy screen with sample campaign or affiliate groups. | Organize links with groups so campaigns and affiliate links stay easy to manage. |

When the screenshot files are added, insert this section in `readme.txt` after the FAQ section and before Changelog:

```text
== Screenshots ==

1. Manage branded short links from the CleanLinks list, including target URLs and click totals.
2. Set the destination URL for each branded link from the Redirection Settings panel.
3. Import supported links or export your CleanLinks data from the Import/Export screen.
4. Organize links with groups so campaigns and affiliate links stay easy to manage.
```

## Validation Path

Run these checks before marking #57 release-ready:

```bash
git diff --check
find .wordpress-org -maxdepth 1 -type f | sort | xargs file
```

Manual release-candidate checks:

- Confirm `readme.txt` still has no more than five tags.
- Confirm `readme.txt` keeps `Stable tag: 1.0.3` until the owner-approved release action.
- Confirm `Tested up to: 7.0` is present in `readme.txt`.
- Validate the final `readme.txt` with the official WordPress.org readme validator or an equivalent release checklist.
- Confirm every screenshot caption has a matching `.wordpress-org/screenshot-N.png` file.
- Confirm the WordPress.org deploy package includes `.wordpress-org` assets through the deployment path, not only the GitHub archive.

## Stop Condition

This package becomes release-ready when screenshot files are captured from the target release UI, captions are added to `readme.txt`, readme/package validation passes, and the owner is asked for the exact production release and WordPress.org deploy approval.
