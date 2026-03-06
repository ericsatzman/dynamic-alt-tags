=== Dynamic Alt Tags ===
Contributors: ericsatzman
Tags: accessibility, images, alt text, ai
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate and manage AI-suggested alt text for WordPress images using a Cloudflare Worker endpoint.

== Description ==
Dynamic Alt Tags provides an admin-first workflow for generating, reviewing, and applying image alt text suggestions.

Key features:
* Queue-based processing for media library images.
* Cloudflare Worker provider integration for AI alt suggestions.
* Queue review actions: Approve, Skip Image, and Generate Alt Text.
* Queue and history flow for reviewed items.
* Attachment details button to Generate Alt Text directly into the Alternative Text field.
* Mobile/tablet responsive queue layout improvements.
* Cleanup of plugin queue/meta data when an attachment is deleted.
* Role-based access control for queue visibility and actions.

Request/response contract:
* Sends POST JSON to worker with: `image_url`, `context`, `rules`
* Optional bearer token authorization
* Accepts JSON response with `alt_text` (or `caption`) and optional confidence

== Installation ==
1. Upload the plugin to `/wp-content/plugins/dynamic-alt-tags`.
2. Activate it from Plugins.
3. Go to Settings > Dynamic Alt Tags.
4. Add your Worker URL and token, then click Test Provider Connection.
5. Process queue items from Settings or review from Media > Dynamic Alt Tags.

== Usage ==
1. Open Settings > Dynamic Alt Tags and configure provider credentials.
2. Run queue processing from Settings and monitor progress.
3. Review pending suggestions in Media > Dynamic Alt Tags.
4. In Attachment Details, click Generate Alt Text to generate and apply alt text.

Detailed documentation:
* See `USER-GUIDE.md` in this plugin directory for a full setup and workflow guide.

== Permissions ==
Administrators always have full access. Additional roles can be granted queue-page access from Settings > Dynamic Alt Tags under Access Control.

== Changelog ==
= 0.1.0 =
* Initial MVP.
