# CleanLinks – Link Cloaking Plugin for WordPress

Contributors: mehul0810, ankur0812
Tags: link cloaking, link shortener, branded links, affiliate links, url shortener
Donate link: [Buy Me a Coffee](https://www.buymeacoffee.com/mehulgohil)
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.2
License: GPLv3
License URI: [GPLv3](http://www.gnu.org/licenses/gpl-3.0.html)

CleanLinks is the fastest, easiest way to cloak affiliate links and manage your affiliate links from a single place.

## Description

**CleanLinks** is the powerful WordPress plugin for affiliate link cloaking, and link management – designed for bloggers, marketers, and anyone who wants full control of their outbound links.

Tired of long, messy affiliate URLs? CleanLinks lets you cloak, brand, and organize your links under your own domain (e.g. `yourdomain.com/recommends/offer`). You can view per-link click counts and export link details to CSV.

Whether you’re tracking affiliate conversions, simplifying URLs for social sharing, or organizing outbound links for SEO, CleanLinks is your all-in-one link shortener and redirect solution.

### Features at a Glance

- **Branded Link Shortener:** Create memorable, clean short links under your own domain.
- **Affiliate Link Cloaking:** Cloak affiliate and referral links for higher trust and better CTR.
- **CSV Export:** Download published link IDs, titles, short URLs, and destination URLs in a CSV file. Click counts are not included.
- **Organize with Groups:** Group and search your links for effortless management.
- **Nofollow Options:** Easily add `nofollow`.
- **SEO & Performance Optimized:** Fast, lightweight, and compatible with top SEO/caching plugins.
- **Developer Friendly:** 100% GPL, extendable via WordPress hooks and filters.

### Who is CleanLinks for?

- Affiliate marketers wanting better link tracking and cloaking
- Bloggers and content creators sharing branded, memorable URLs
- Agencies managing multiple client links or campaigns
- Anyone who wants full control over outbound links in WordPress

## Installation

### Automatic Installation

1. In your WordPress dashboard, go to **Plugins → Add New**.
2. Search for "CleanLinks".
3. Click "Install Now" and then activate.

### Manual Installation

1. Download the CleanLinks plugin ZIP.
2. Upload it to your `/wp-content/plugins/` directory via FTP.
3. Activate the plugin via **Plugins** in the WordPress dashboard.
4. Start managing your links from the new **CleanLinks** menu.

## Frequently Asked Questions

### Can I export my links?

Yes. The CSV includes each published link's ID, title, short URL, and destination URL. It does not include click counts.

### Does CleanLinks support affiliate links?

Absolutely. CleanLinks is built for affiliate marketers, letting you cloak and track affiliate URLs with ease.

### Is click tracking available?

Yes. The links list shows a total click count for each published link.

### Is CleanLinks compatible with SEO and caching plugins?

Yes, it works seamlessly with leading SEO and caching plugins for best performance.

### Can I use my own domain for short links?

Yes, all links use your own site’s domain for maximum trust and branding.

## Screenshots

1. CleanLinks admin panel – Manage all branded links from one place.
2. Add/Edit link – Custom slug, destination URL, and nofollow option.
3. Export to CSV – Download link IDs, titles, short URLs, and destination URLs.

## Changelog

### 1.1.2

- Show a saved-link overview and validate destination URLs in the Add/Edit Link editor.
- Keep an existing destination when an edited URL is rejected, and preserve multiple query parameters in saved redirects.
- Exclude CleanLinks links and group names from WordPress general exports while retaining the dedicated published-links CSV export.
- Pin GitHub Actions to verified commits, restrict workflow token permissions, and check new high or critical npm advisory changes.
- Correct descriptions of redirect and CSV behavior.

### 1.1.1

- **Security Improvements:** Neutralize spreadsheet formula-leading values in CSV exports and remediate Composer development-tool security advisories.
- **Improved URL Handling:** Reject malformed redirect URL values without fatal errors or unintended metadata changes.
- **Bounded Export Cache Cleanup:** Stream large CSV exports in bounded batches, clean page caches as they are processed, and remove temporary files on completion or failure.
- **Click Counter Reliability:** Update redirect click counters atomically and invalidate cached counts after clicks are recorded.
- Confirm compatibility through WordPress 7.1.

### 1.1.0

- Count redirect clicks only for published CleanLinks to avoid analytics changes for drafts and non-public links.
- Add WordPress.org icon and banner assets for the CleanLinks plugin listing.
- Confirm release metadata compatibility through WordPress 7.0.

### 1.0.0

- Initial release – Create branded short links, cloak affiliate URLs, enable click tracking, and export links to CSV.

## Upgrade Notice

### Upgrade to 1.1.2

WordPress Tools > Export no longer includes CleanLinks links or groups. Use the CleanLinks Export tool for a CSV of published links.

### Upgrade to 1.1.0

Click tracking now records redirects only for published CleanLinks; draft and non-public links continue to avoid analytics side effects.

### Upgrade to 1.0.0

First stable version of CleanLinks. Includes branded short links, affiliate link cloaking, CSV export, and basic analytics.

## Roadmap

- **Advanced Analytics:** Unique clicks, referrers, device data (Pro)
- **Geo-redirects & A/B Testing** (Pro)
