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
Dynamic Alt Tags provides a professional, admin-first workflow for generating, reviewing, and applying AI alt text suggestions for Media Library images.

=== Key Features ===
* Queue-based processing for media library images.
* Cloudflare Worker provider integration for AI alt suggestions.
* Queue review actions: Approve, Skip Image, and Generate Alt Text.
* Queue and history flow for reviewed items.
* Attachment details button to Generate Alt Text directly into the Alternative Text field.
* Mobile/tablet responsive queue layout improvements.
* Cleanup of plugin queue/meta data when an attachment is deleted.
* Role-based access control for queue visibility and actions.

=== Worker Request/Response Contract ===
* Sends POST JSON to worker with: `image_url`, `context`, `rules`
* Optional bearer token authorization
* Accepts JSON response with `alt_text` (or `caption`) and optional confidence

== Installation ==
1. Upload the plugin to `/wp-content/plugins/dynamic-alt-tags`.
2. Activate it from Plugins.
3. Go to `Settings > Dynamic Alt Tags`.
4. Add your Worker URL and token, then click `Test Provider Connection`.
5. Process queue items from Settings or review from Media > Dynamic Alt Tags.

== Usage ==
=== Quick Start ===
1. Open `Settings > Dynamic Alt Tags` and configure provider credentials.
2. Run queue processing from Settings and monitor progress.
3. Review pending suggestions in `Media > Dynamic Alt Tags`.
4. In Attachment Details, click Generate Alt Text to generate and apply alt text.

=== Access and Permissions ===
==== Settings Page ====
* Administrators only.

==== Queue Page ====
* Administrators always have access.
* Additional roles can be granted access from:
  * `Settings > Dynamic Alt Tags > Access Control`
  * `Roles Allowed To Access Dynamic Alt Tags`

== Permissions ==
Administrators always have full access. Additional roles can be granted queue-page access from Settings > Dynamic Alt Tags under Access Control.

== User Guide ==
=== Queue Workflow ===
Go to `Media > Dynamic Alt Tags`.

==== Active Queue ====
Use this tab to process and review pending items.

Actions:
* `Generate Alt Text`: Generates or refreshes a suggestion for the item.
* `Approve`: Applies suggested alt text.
* `Skip Image`: Moves item to History without applying.
* `View Image`: Opens the source image in a new tab.

==== History ====
Shows finalized items (`approved`, `rejected`, `skipped`) with timestamp and final alt text.

==== No Alt Images ====
Lists image attachments that currently have no alt text.

Actions:
* `Add to Queue` or `Requeue`
* `View Image`

=== Attachment Details Workflow ===
In Media Attachment Details, use `Generate Alt Text` to generate and apply alt text quickly.

Behavior:
* If no queue row exists: item is queued and processed.
* If status is `generated`: suggested alt is applied.
* If status is `processing`: plugin asks you to try again shortly.
* If status is `skipped`: item is automatically requeued and processed.
* If status is `approved` or `rejected`: requeue from the Queue page is required.

=== Settings Reference ===
==== Provider and Processing ====
* `Cloudflare Worker URL`: endpoint used for caption generation.
* `Cloudflare API Token`: optional bearer token for protected Worker endpoints.
* `Batch Size`: number of queue items processed per run.
* `Min Confidence`: confidence threshold used when manual review is disabled.

==== Workflow Controls ====
* `Use URL Mode - Send Image URL`
  * Enabled: Worker fetches image by URL.
  * Disabled (default): direct upload mode (recommended for local/private environments).
* `Auto-Apply Alt Text for New Uploads`
  * Automatically approves generated text for new uploads.
* `Sync Alt Text to Attachment Title`
  * Updates attachment title when alt text is applied.
* `Overwrite Existing Alt Text`
  * Allows replacing existing alt text.
* `Require Manual Review`
  * Keeps suggestions in review state until approved.
* `Keep Data On Delete`
  * Preserves plugin data on uninstall.

=== Troubleshooting ===
==== Provider Connection Fails ====
* Confirm Worker URL is correct and reachable.
* Confirm token is valid (if required).
* Run `Test Provider Connection` again.

==== Nothing Processes in Queue ====
* Check queue status counts in Active Queue.
* Verify items are not already in `generated` waiting for review.
* Verify provider/network access from the WordPress host.

==== URL Mode Fails in Local/Dev ====
* Local/private hostnames may not be reachable by Worker.
* Switch to direct upload mode (`Use URL Mode` unchecked).

=== Best Practices ===
* Start with `Require Manual Review` enabled.
* Use conservative `Batch Size` during initial rollout.
* Review `History` regularly for quality checks.
* Grant queue access only to roles responsible for media metadata.

=== Support Notes ===
If debugging is needed, enable `WP_DEBUG` temporarily and reproduce one action to capture provider/processing detail in logs.

== Changelog ==
= 0.1.0 =
* Initial MVP.
