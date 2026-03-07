# Dynamic Alt Tags

Generate and manage AI-suggested alt text for WordPress images using a Cloudflare Worker endpoint.

## Overview
Dynamic Alt Tags provides an admin-first workflow for generating, reviewing, and applying AI image alt text suggestions.

### Key Features
- Queue-based processing for media library images
- Cloudflare Worker integration for AI alt suggestions
- Queue review actions: **Approve**, **Skip Image**, **Generate Alt Text**
- Queue and history workflow for review tracking
- Attachment Details action to generate/apply alt text
- Mobile/tablet responsive queue layout
- Role-based access control for queue access

## Requirements
- WordPress 6.2+
- PHP 7.4+
- Accessible Cloudflare Worker endpoint
- Optional Cloudflare API token

## Installation
1. Upload the plugin to `/wp-content/plugins/dynamic-alt-tags`.
2. Activate it from **Plugins**.
3. Go to **Settings > Dynamic Alt Tags**.
4. Add your Cloudflare Worker URL and Cloudflare token.
5. Click **Test Provider Connection** and save.

## Usage
### Quick Start
1. Open **Settings > Dynamic Alt Tags** and verify provider settings.
2. Process queue items from Settings or **Media > Dynamic Alt Tags**.
3. Review suggestions and approve/skip as needed.
4. In Attachment Details, click **Generate Alt Text** for one-off generation.

### Queue Workflow
Go to **Media > Dynamic Alt Tags**.

#### Active Queue
- **Generate Alt Text**: generate/refresh suggestion
- **Approve**: apply suggested alt text
- **Skip Image**: move item to History
- **View Image**: open source image

#### History
Shows finalized items (`approved`, `rejected`, `skipped`) with confidence and processed timestamp.

#### No Alt Images
Lists images with empty alt text and lets you queue/requeue quickly.

### Attachment Details Workflow
- No queue row: image is queued and processed
- `generated`: suggested alt text is applied
- `processing`: try-again message is shown
- `skipped`: automatically requeued and processed
- `approved` / `rejected`: must be requeued from Queue page

## Access and Permissions
### Settings
- Administrators only

### Queue
- Administrators always have access
- Additional roles can be granted queue access in **Settings > Dynamic Alt Tags > Access Control**

## Settings Reference
- Cloudflare Worker URL
- Cloudflare API Token
- Batch Size
- Min Confidence
- Use URL Mode - Send Image URL
- Auto-Apply Alt Text for New Uploads
- Sync Alt Text to Attachment Title
- Overwrite Existing Alt Text
- Require Manual Review
- Keep Data On Delete

## Troubleshooting
### Provider Test Fails
- Verify Worker URL/token
- Verify endpoint reachability from WordPress host
- Re-run **Test Provider Connection**

### Queue Does Not Process
- Check if items are already `generated` and waiting for review
- Verify provider/network availability
- Try processing a single row first

### URL Mode Issues on Local/Private Sites
- Worker may not reach local/private URLs
- Disable URL mode and use direct upload mode

## Changelog
### 0.1.0
- Initial MVP

---

**Note:** `readme.txt` remains the WordPress.org-compatible readme source.
