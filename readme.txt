=== CleanLinks ===
Contributors: mehul0810, ankur0812
Tags: link cloaking, link branding, affiliate links, link shortener, redirect manager
Donate link: https://www.buymeacoffee.com/mehulgohil
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create branded short links, manage redirects, cloak affiliate URLs, and export links via CSV – all from your WordPress dashboard.

== Description ==

**CleanLinks** is your all-in-one solution for creating branded, customizable short links directly within WordPress. Whether you’re managing affiliate campaigns, simplifying external URLs, or just looking for a professional way to share links, CleanLinks makes it easy.

Designed to be lightweight, CleanLinks creates 301 redirects for links with a destination URL. It records link clicks, organizes links into groups, and exports link details to CSV. If a link has no destination URL, it redirects to the site home page with a 302 response.

= Key Features =

- **Branded Short Links** – Cloak long URLs under your own domain.
- **CSV Export Tool** – Download published link IDs, titles, short URLs, and destination URLs in a CSV file. Click counts are not included.
- **Click Tracking** – View total clicks for published links.
- **Organize Your Links** – Group links with tags for easy management.
- **SEO-Friendly** – Compatible with SEO and caching plugins.
- **Developer Friendly** – Built using WordPress standards with hooks/filters.

= Minimum Requirements =

- WordPress 5.5 or higher
- PHP 8.1 or higher
- MySQL 5.5 or higher

= Automatic Installation =

1. Go to your WordPress dashboard.
2. Navigate to Plugins → Add New.
3. Search for "CleanLinks".
4. Click "Install Now" and activate.

= Manual Installation =

1. Download the CleanLinks plugin.
2. Upload it to your `/wp-content/plugins/` directory via FTP or File Manager.
3. Activate CleanLinks from the "Plugins" screen in your WordPress dashboard.
4. Navigate to **CleanLinks** menu to start managing your branded links.

== Frequently Asked Questions ==

= Can I export my published links? =
Yes. The Export tool downloads a CSV with each published link's ID, title, short URL, and destination URL. It does not include click counts.

= Does CleanLinks support affiliate links? =
Yes. You can create short links for affiliate destinations. Links with a destination use a 301 redirect, and you can organize them into groups.

= Can I track clicks on my links? =
Yes. Click tracking is available per link.

= Is it compatible with caching and SEO plugins? =
Yes, CleanLinks is fully compatible with popular caching and SEO plugins.

== Screenshots ==

1. Admin interface showing branded link management.
2. Link settings with a destination URL and nofollow option.
3. Export tool with one-click CSV export.
4. Total click counts in the links list.

== Changelog ==

= 1.1.1 =
* **Security Improvements:** Neutralize spreadsheet formula-leading values in CSV exports and remediate Composer development-tool security advisories.
* **Improved URL Handling:** Reject malformed redirect URL values without fatal errors or unintended metadata changes.
* **Bounded Export Cache Cleanup:** Stream large CSV exports in bounded batches, clean page caches as they are processed, and remove temporary files on completion or failure.
* **Click Counter Reliability:** Update redirect click counters atomically and invalidate cached counts after clicks are recorded.
* Confirm compatibility through WordPress 7.1.

= 1.1.0 =
* Count redirect clicks only for published CleanLinks to avoid analytics changes for drafts and non-public links.
* Add WordPress.org icon and banner assets for the CleanLinks plugin listing.
* Confirm release metadata compatibility through WordPress 7.0.

= 1.0.3 =
* Update blueprint.json

= 1.0.2 =
* Update blueprint.json

= 1.0.1 =
* Update blueprint.json

= 1.0.0 =
* Initial release.
* Create and manage branded links.
* One-click export to CSV.
* Built-in click tracking.
* Lightweight and SEO-friendly.

== Upgrade Notice ==

= 1.1.0 =
Click tracking now records redirects only for published CleanLinks; draft and non-public links continue to avoid analytics side effects.

= 1.0.0 =
Initial version of CleanLinks with CSV export, link tracking, and redirect features.
