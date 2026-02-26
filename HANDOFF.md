# Dynamic Alt Tags - Project Handoff

## 1) Project Overview
Dynamic Alt Tags is a WordPress plugin that queues image attachments, requests AI alt text from a Cloudflare Worker, and supports admin review/apply/reject/skip workflows from both queue pages and the Media attachment modal.

Local plugin path:
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags`

GitHub repository:
- `https://github.com/ericsatzman/dynamic-alt-tags.git`
- Branch: `main`
- Repo root is now the plugin folder itself (no `wp-content/...` prefix in tracked paths).

Latest known remote commit at this handoff:
- `d6a9d46` - Fix Dynamic Alt Tags queue UI

## 2) What Is Implemented

### Core architecture
- Bootstrap: `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/dynamic-alt-tags.php`
- Hook wiring: `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-plugin.php`
- Activation and queue table: `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-activator.php`

### Queue and processing
- Queue repository and status transitions: `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-queue-repo.php`
- Processor loop and provider call handling: `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-processor.php`
- Alt text normalization and quality checks: `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-alt-generator.php`
- Queue cleanup when media items are deleted.
- Only unprocessed images are queued.

### Provider integration
- Interface: `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-provider-interface.php`
- Cloudflare implementation: `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-provider-cloudflare.php`
- Request format: POST JSON (`image_url`, `context`, `rules`) with optional `Authorization: Bearer <token>`
- Response format: accepts `alt_text` (or `caption`) plus optional numeric confidence.

### Admin and media UX
- Settings page with provider test and queue processing controls.
- Queue page with review/history behavior and bulk actions.
- Queue no longer shows items once approved/rejected/skipped (those move to history flow).
- Attachment modal integration:
  - Shows Dynamic Alt Tags panel persistently on reopen.
  - Supports approve/reject/skip/custom alt action flow.
  - Apply action updates/saves alt handling via AJAX.
- Admin visibility restricted to WordPress administrators.

## 3) Behavior Notes

### Queue statuses
- `queued`, `processing`, `generated`, `approved`, `rejected`, `failed`, `skipped`

### Skip behavior
- UI label is "Skip Image"
- Skipping sets empty alt text and marks queue item `skipped`.

### Processing and progress UX
- Manual processing on Settings uses AJAX-style progress UI.
- On completion, the page can refresh and show a completion notice.

## 4) Cloudflare Worker Context
Configured worker:
- `dynamic-alt-tags-worker`
- `https://dynamic-alt-tags-worker.eric-satzman.workers.dev/`

Common failure cases:
1. Token mismatch causing `401 Unauthorized`
2. Worker cannot fetch non-public/local image URLs
3. Missing or incorrectly named Workers AI binding (`AI`)

## 5) Repository and Workspace Notes

### Repo layout (current)
- Git root is plugin root: `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags`
- Expected tracked files are plugin-only files at repo root (`admin/`, `assets/`, `includes/`, etc.).

### Local WP environment caveat
- The broader WordPress tree contains many unrelated files; ignore those for this repository.

## 6) Resume Checklist
1. Confirm current state:
   - `git -C /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags log --oneline -n 5`
2. Validate PHP syntax after edits:
   - `find /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags -name '*.php' -print0 | xargs -0 -n1 php -l`
3. In WordPress admin:
   - Media -> Dynamic Alt Tags Settings -> Test Provider Connection
   - Run queue processing and verify progress/completion
   - Check Queue and attachment modal actions (approve/reject/skip/custom)
4. If provider failures continue:
   - Review Cloudflare Worker logs for POST requests and status codes.

## 7) Plugin File Inventory
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/HANDOFF.md`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/dynamic-alt-tags.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/readme.txt`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/uninstall.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/languages/dynamic-alt-tags.pot`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/assets/admin.css`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/assets/admin.js`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/admin/views-page-settings.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/admin/views-page-queue.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-activator.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-admin.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-alt-generator.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-logger.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-plugin.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-processor.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-provider-cloudflare.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-provider-interface.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-queue-repo.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-rest.php`
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-settings.php`
