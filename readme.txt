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
This plugin creates a queue for image attachments, calls your Cloudflare Worker to generate caption suggestions, and lets admins review/approve alt text safely.

== Installation ==
1. Upload the plugin to `/wp-content/plugins/dynamic-alt-tags`.
2. Activate it from Plugins.
3. Go to Media > Dynamic Alt Tags Settings.
4. Add your Worker URL and token.

== Changelog ==
= 0.1.0 =
* Initial MVP.
