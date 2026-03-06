# Dynamic Alt Tags User Guide

## Overview
Dynamic Alt Tags helps WordPress teams generate, review, and apply image alt text using a Cloudflare Worker.

The plugin is designed for an editorial workflow:
- Generate suggestions in bulk from a queue
- Review and approve or skip suggestions
- Generate directly from Attachment Details when needed

## Requirements
- WordPress `6.2+`
- PHP `7.4+`
- An accessible Cloudflare Worker endpoint for alt text generation
- Optional: Cloudflare API token (if your Worker requires it)

## Installation and Initial Setup
1. Install and activate the plugin in WordPress.
2. Go to `Settings > Dynamic Alt Tags`.
3. Configure:
   - `Cloudflare Worker URL`
   - `Cloudflare API Token` (if required)
   - `Batch Size` and `Min Confidence` (optional tuning)
4. Click `Test Provider Connection`.
5. Save settings.

## Access and Permissions
### Settings page
- Administrators only.

### Queue page
- Administrators always have access.
- Additional roles can be granted access from:
  - `Settings > Dynamic Alt Tags > Access Control`
  - `Roles Allowed To Access Dynamic Alt Tags`

## Main Workflow (Queue)
Go to `Media > Dynamic Alt Tags`.

### Active Queue
Use this tab to process and review pending items.

Actions:
- `Generate Alt Text`: Generates/refreshes a suggestion for the item
- `Approve`: Applies suggested alt text
- `Skip Image`: Moves item to History without applying
- `View Image`: Opens the source image in a new tab

### History
Shows finalized items (`approved`, `rejected`, `skipped`) with timestamp and final alt text.

### No Alt Images
Lists image attachments that currently have no alt text.

Actions:
- `Add to Queue` or `Requeue`
- `View Image`

## Attachment Details Workflow
In Media Attachment Details, use `Generate Alt Text` to generate and apply alt text quickly.

Behavior:
- If no queue row exists: item is queued and processed
- If status is `generated`: suggested alt is applied
- If status is `processing`: plugin asks you to try again shortly
- If status is `skipped`: item is automatically requeued and processed
- If status is `approved` or `rejected`: plugin requires requeue from the Queue page

## Settings Reference
- `Use URL Mode - Send Image URL`
  - On: Worker fetches by URL
  - Off (default): direct upload mode (recommended for local/private environments)
- `Auto-Apply Alt Text for New Uploads`
  - Automatically approves generated text for new uploads
- `Sync Alt Text to Attachment Title`
  - Updates attachment title when alt text is applied
- `Overwrite Existing Alt Text`
  - Allows replacing existing alt text
- `Require Manual Review`
  - Keeps suggestions in review state until approved
- `Keep Data On Delete`
  - Preserves plugin data on uninstall

## Troubleshooting
### Provider connection fails
- Confirm Worker URL is correct and reachable
- Confirm token is valid (if required)
- Run `Test Provider Connection` again

### Nothing processes in queue
- Check queue status counts in Active Queue
- Verify the item is not already in `generated` waiting for review
- Verify provider/network access from the WordPress host

### URL mode issues on local/dev sites
- Local/private hostnames may not be reachable by Worker
- Switch to direct upload mode (`Use URL Mode` unchecked)

## Operational Best Practices
- Start with `Require Manual Review` enabled
- Use conservative `Batch Size` during initial rollout
- Review `History` regularly for quality checks
- Grant queue access only to editorial roles that manage media metadata

## Support Notes
If debugging is needed, enable `WP_DEBUG` temporarily and reproduce one action to capture provider/processing detail in logs.
