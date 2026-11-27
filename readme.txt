=== CleanLinks ===
Contributors: mehul0810, ankur0812
Tags: link cloaking, link branding, affiliate links, link shortener, redirect manager
Donate link: https://www.buymeacoffee.com/mehulgohil
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create branded short links on your WordPress site. Group links, count clicks, and export published links to CSV.

== Description ==

Create short links for affiliate URLs, campaigns, and resources you share often. Open **CleanLinks** in your WordPress dashboard to add and manage links on your site's domain.

= Key Features =

- **Groups:** Organize related links together.
- **Click counts:** View total clicks for each published link in the links list.
- **CSV export:** Download published links from **CleanLinks > Export**.
- **Nofollow:** Optionally send an `X-Robots-Tag: nofollow` header with a redirect.

= Minimum Requirements =

- WordPress 5.5 or higher
- PHP 8.1 or higher

= Automatic Installation =

1. Go to your WordPress dashboard.
2. Navigate to Plugins → Add New.
3. Search for "CleanLinks".
4. Click "Install Now" and activate.

= Manual Installation =

1. Download the CleanLinks plugin.
2. Upload it to your `/wp-content/plugins/` directory via FTP or File Manager.
3. Activate CleanLinks from the "Plugins" screen in your WordPress dashboard.
4. Open **CleanLinks** in your WordPress dashboard.

== Frequently Asked Questions ==

= How do I export my links? =
Go to **CleanLinks > Export** to download a CSV of published links. The file includes each link's ID, title, short URL, and destination URL. It does not include click counts.

= Does CleanLinks support affiliate links? =
Yes. Add your affiliate URL, including its query parameters, as the destination for a short link.

= What happens when someone follows a short link? =
A published link with a destination sends a permanent (301) redirect. If a published link has no destination, it sends a temporary (302) redirect to your homepage.

== Changelog ==

= 1.1.2 =
* See the saved destination, publication status, and total clicks in the link editor. Copy the short URL after publishing.
* See whether copying a short URL succeeded or failed.
* Preserve encoded characters and repeated query parameters in redirect destinations.
* Keep the saved destination when WordPress rejects a URL edit.
* Exclude CleanLinks links and groups from Tools > Export. Published links are still available as CSV from CleanLinks > Export.

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

= 1.1.2 =
WordPress Tools > Export no longer includes CleanLinks links or groups. Use the CleanLinks Export tool for a CSV of published links.

= 1.1.0 =
Click tracking now records redirects only for published CleanLinks; draft and non-public links continue to avoid analytics side effects.

= 1.0.0 =
Initial version of CleanLinks with CSV export, link tracking, and redirect features.
