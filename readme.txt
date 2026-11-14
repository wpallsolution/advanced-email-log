=== Advanced Email Log ===
Contributors: hplugin
Donate link: https://wpallsolution.com/
Tags: email log, email log export, wp_mail, email tracking, email debug
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal, lightweight email logger for WordPress. Tracks wp_mail events with status tracking, search, filters, CSV export, and email preview.

== Description ==

**Advanced Email Log** is a universal, lightweight, and secure email logging plugin engineered for WordPress administrators, developers, and store owners. It reliably captures outgoing email activity passing through the standard WordPress `wp_mail()` pipeline.

Whether you are debugging form submissions, tracking customer order receipts, or verifying notifications, Advanced Email Log gives you complete visibility into every email attempt.

### Universal WordPress Compatibility
Advanced Email Log captures email activity triggered by:
* WordPress Core (new user notices, password resets, comment notifications)
* WooCommerce (order receipts, customer updates, inventory alerts)
* Form Plugins (Contact Form 7, WPForms, Fluent Forms, Forminator, Gravity Forms, Ninja Forms, Elementor Forms)
* Any theme or plugin utilizing standard `wp_mail()`

### Key Features (100% Free)
* **Reliable Email Logging:** Intercepts `wp_mail` with zero duplicates, capturing timestamp, To, From, Subject, body, content type, CC, BCC, Reply-To, attachment metadata, triggering IP, status, and diagnostic transport errors.
* **Accurate Status Tracking:** Clearly distinguishes **Accepted**, **Failed**, **Pending**, and **Unknown**. Note: `wp_mail_succeeded` confirms the mail transport accepted the message; it does not guarantee recipient inbox delivery.
* **Original, Clean List Table:** Filter by date presets (Today, Last 7 Days, Last 30 Days, This Month) or custom date ranges, search by recipient or subject, and sort by date or status.
* **Safe HTML & Plain-Text Preview:** Inspect full email messages inside a strongly sandboxed, script-disabled iframe to protect your admin dashboard against malicious email contents.
* **Bulk & Filtered CSV Export:** Stream email logs directly to UTF-8 CSV with built-in protection against CSV formula injection.
* **Diagnostic Test Email:** Send a branded test email directly through `wp_mail()` to verify server transport health and immediately link to the generated log.
* **User Roles Access Control:** Allow administrators to grant log-viewing access (`ael_view_logs`) to custom or built-in roles while keeping settings strictly restricted to administrators.
* **Privacy & GDPR Compliance:** Integrates with WordPress Core Privacy Tools (Export Personal Data & Erase Personal Data) and provides an optional clean-up toggle on uninstall.
* **Dashboard Activity Widget:** Optional lightweight summary widget showing daily email activity without slowing down your admin area.
* **Live Updates:** Optional lightweight polling notifies you when new emails arrive without requiring full-page reloads.

== Installation ==

1. Upload the `advanced-email-log` folder to your `/wp-content/plugins/` directory, or install directly via the WordPress Plugins menu.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Advanced Email Log** in your admin menu to view email activity.
4. Send a test email from the **Test Email** tab to verify your configuration.

== Frequently Asked Questions ==

= Does "Accepted" mean the email was delivered to the user's inbox? =
No. In WordPress, "Accepted" means `wp_mail()` successfully handed off the email to your web server's mail system or configured SMTP provider without transport errors. Receiving mail servers (such as Gmail, Yahoo, or Outlook) may still filter or delay emails based on SPF, DKIM, DMARC, or content.

= Does this plugin send emails through its own mail servers? =
No. Advanced Email Log does not alter your email routes, does not send emails through third-party relays, and does not require external API keys. It simply observes your native WordPress mail flow.

= Does the plugin store actual file attachments? =
No. To preserve server storage and protect user privacy, Advanced Email Log only records safe attachment metadata (filename, file size, and MIME type). It never duplicates or stores actual attachment files on disk.

= How does Advanced Email Log prevent CSV formula injection? =
During CSV export, any cell values starting with dangerous spreadsheet formula characters (`=`, `+`, `-`, `@`, `\t`, `\r`) are automatically prepended with a single quote to neutralize formula execution in Microsoft Excel and Google Sheets.

= Will uninstalling delete my email logs? =
By default, Advanced Email Log preserves your historical logs upon uninstallation so no data is accidentally lost. If you want all database tables and options deleted when removing the plugin, enable the **Remove plugin data on uninstall** option in Settings &rarr; Privacy & Data before deleting the plugin.

== Screenshots ==

1. Email Logs screen featuring comprehensive columns, status badges, date filtering, and search.
2. View Email Details dialog with overview metadata, headers, and sandboxed HTML preview.
3. Test Email diagnostic tool verifying wp_mail transport handoff.
4. Settings page for General, User Roles, Privacy & Data, and Performance options.
5. Recommended Plugins tab highlighting official WP All Solution extensions.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Universal `wp_mail` logging pipeline with correlation stack.
* Dedicated custom table `ael_email_logs` with versioned migration.
* Sandboxed HTML email preview and raw body viewer.
* Streaming CSV export with formula injection protection.
* Branded diagnostic test email tool.
* Role-based access control and WordPress Privacy Tools integration.
