# Dynamic Alt Tags - Project Handoff

## 1) Project Overview
Dynamic Alt Tags is a WordPress plugin that queues image attachments, requests AI alt text from a Cloudflare Worker, and supports admin review/apply/reject/skip/process workflows from both queue pages and the Media attachment modal.

Local plugin path:
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags`

GitHub repository:
- `https://github.com/ericsatzman/dynamic-alt-tags.git`
- Branch: `main`
- Git root is the plugin folder itself (tracked paths start at `admin/`, `assets/`, `includes/`, etc.).

Current latest commit at handoff:
- `4d7fdea` - Enhance queue UI with no-alt tab and dynamic loading

Recent commit history (newest first):
1. `4d7fdea` - Enhance queue UI with no-alt tab and dynamic loading
2. `fe96080` - Add per-image queue processing progress UI
3. `41104a8` - Improve queue controls and provider diagnostics
4. `d0dce7f` - Improve settings diagnostics and queue processing feedback
5. `5c053e3` - Update handoff and readme for plugin-root repo

## 2) Current Functional Scope

### Core architecture
- Bootstrap: `dynamic-alt-tags.php`
- Hook registration/container: `includes/class-plugin.php`
- Activation/deactivation + queue table: `includes/class-activator.php`
- Settings model + rendering: `includes/class-settings.php`
- Queue repository/data layer: `includes/class-queue-repo.php`
- Processor orchestration: `includes/class-processor.php`
- Provider abstraction + Cloudflare provider: `includes/class-provider-interface.php`, `includes/class-provider-cloudflare.php`
- Admin page handlers + AJAX endpoints: `includes/class-admin.php`

### Queue statuses
- `queued`, `processing`, `generated`, `approved`, `rejected`, `failed`, `skipped`

### Main queue UX (Media -> Dynamic Alt Tags Queue)
Tabs:
1. **Active Queue**
- Rows in statuses: `queued`, `processing`, `generated`, `failed`
- Bulk actions: Approve / Reject / Skip Image
- Row actions: Approve / Reject / Skip Image / View Image / Process (status-based)

2. **History**
- Rows in statuses: `approved`, `rejected`, `skipped`
- Read-only history view

3. **No Alt Images** (new)
- Lists image attachments with empty `_wp_attachment_image_alt`
- Row action: `Add to Queue`
- Shows queue status if already queued previously (via join to queue table)

### Queue page top-right actions (Active tab)
- `Run Backfill`
- `Process Queue Now`

Both buttons are available from the Queue page (not just Settings) and redirect back with queue-page notices.

### Pagination and dynamic loading
- Server loads **20 items initially** per tab.
- New `Add more` button at bottom of table for all tabs.
- AJAX appends +20 each click until all available rows are shown.

### Per-image process progress (Queue page)
- Row `Process` uses AJAX (`ai_alt_queue_process_ajax`).
- Inline progress bar shown below row action buttons.
- Row status/confidence/suggested alt updates on success.
- Progress bar and message are cleared together shortly after completion.

## 3) Settings Page Behavior

### Connection status panel
- Panel appears below Tools only on provider test flow (`notice=provider_test`).
- Panel is dismissible.
- Includes:
  - Connected / Connection Error
  - Last checked timestamp
  - Latest queue failure summary

### Provider test behavior
`Test Provider Connection` now runs two checks:
1. Baseline public image test
2. Latest queued image test (attachment URL from latest active queue row)

This catches local/firewall URL reachability issues that baseline alone would miss.

### Process Queue Now (Settings)
- Uses iterative AJAX chunking.
- Handles partial completion better (reduced hard-fail UX when some rows already processed).
- Distinct notices:
  - `process_done`
  - `process_partial`
  - `process_error`

### Save settings confirmation
- Top notice appears: `Settings saved.`

## 4) Provider and Request Behavior (Cloudflare)

### Worker endpoint currently in use
- `https://alt-text-generator.webprod.workers.dev/`

### Auth
- Plugin sends optional `Authorization: Bearer <token>`
- Worker expects secret `WORKER_AUTH_TOKEN` (if auth enabled in Worker code)

### Request modes
1. URL mode
- Sends `image_url`
- Worker fetches URL itself

2. Direct upload mode (new plugin capability)
- Settings toggle: `Direct Upload Mode (Send Image Bytes)`
- Plugin attempts to include:
  - `image_source: bytes`
  - `image_data_base64`
  - `image_mime_type`
  - `image_filename`
- URL is still included for fallback compatibility.

### Direct mode constraints
- Max inline bytes: 10MB (`MAX_INLINE_IMAGE_BYTES`)
- If direct payload cannot be built (missing/unreadable file/size), provider falls back to URL path.

### Timeout
- Provider request timeout raised to **90 seconds**.

### Improved provider error details
- Non-2xx errors now include richer context when available:
  - Worker message/error body snippet
  - upstream fetch status
  - image URL
  - request mode (`bytes` / `url`)
- WP transport errors (e.g., cURL 28) now include request mode as well.

## 5) Worker Setup Notes (important for new context)

### ES module requirement
- Worker must use `export default { async fetch(...) { ... } }` syntax.
- AI binding (`AI`) requires ES module Worker format.

### AI model license acceptance (one-time per account/model)
- For `@cf/meta/llama-3.2-11b-vision-instruct`, one-time prompt `agree` was required.
- Log signal seen: `5016: Thank you for agreeing...`

### Common Worker/runtime failure patterns observed
1. `401 Unauthorized`
- Token mismatch between plugin and Worker secret

2. `400 Image fetch returned non-OK`
- URL mode fetch blocked/unreachable; often local/private URL (`sandbox.local`)

3. `530`/`403` upstream
- Remote host access/WAF/hotlink restrictions for Worker IP/profile

4. `cURL error 28` from plugin
- Timeout to worker or long-running request

### Recommended Worker response fields
For easier plugin diagnostics, Worker should include (on errors where possible):
- `error`/`message`
- `upstream_status`
- `image_url` or `fetch_url`

## 6) Queue and Admin UX Changes Since Early Handoff

### New/changed queue controls
- Row-level `Process` button in Active queue for `queued`, `failed`, `generated`.
- Queue page top-right `Run Backfill` and `Process Queue Now`.
- No Alt Images tab with `Add to Queue`.

### AJAX endpoints added
- `wp_ajax_ai_alt_queue_process_ajax`
- `wp_ajax_ai_alt_queue_load_more_ajax`
- `wp_ajax_ai_alt_queue_add_no_alt_ajax`

### Queue repository methods added
- `enqueue_or_requeue()`
- `get_no_alt_paginated()`
- `get_latest_failed_row()`
- `get_latest_active_row()`
- `get_active_status_counts()`

## 7) Alt Text Generation Rules/Normalization

`includes/class-alt-generator.php` now includes:
- Strip HTML + normalize whitespace
- Remove leading “image/photo/picture of” patterns
- Max length 140 chars with word-boundary trimming
- If truncation happens, trims to last complete sentence ending (`.`, `!`, `?`) when possible
- Basic usability checks remain (minimum length and no filename-like alt)

## 8) Known Caveats / Open Risks

1. **Local dev + URL mode**
- Local URLs like `http://sandbox.local/...` are not fetchable by cloud Worker.
- Use Direct Upload Mode for local/private environments.

2. **No-alt tab render path uses server-rendered HTML helpers**
- Rendering is centralized in admin class helper methods and injected in template/AJAX.
- Functional, but worth future refactor if template concerns should stay in view file.

3. **Large images + direct mode**
- May still be slow even with 90s timeout.
- Can require smaller batch size or per-image processing.

4. **Partial processing UX**
- Improved, but still dependent on server stability/timeouts.

## 9) Resume Checklist (for next Codex session)

1. Confirm git state:
- `git -C /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags log --oneline -n 10`

2. Validate PHP syntax:
- `find /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags -name '*.php' -print0 | xargs -0 -n1 php -l`

3. Validate JS syntax:
- `node --check /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/assets/admin.js`

4. In WP Admin test paths:
- Settings page:
  - Save settings notice
  - Test Provider Connection (baseline + latest queued image checks)
  - Process Queue Now behavior (`done` / `partial` / `error` notices)
- Queue page:
  - Active / History / No Alt Images tabs
  - Add more AJAX for each tab
  - Row Process progress behavior
  - No Alt `Add to Queue` action
  - Top-right Run Backfill + Process Queue Now actions
- Attachment modal:
  - Review panel action flow still intact

5. If provider failures occur:
- Check Worker logs and compare plugin error details for `request mode`, `upstream status`, and `image URL`.

## 10) Important Files to Open First in New Context
- `HANDOFF.md`
- `includes/class-admin.php`
- `includes/class-queue-repo.php`
- `includes/class-provider-cloudflare.php`
- `includes/class-settings.php`
- `admin/views-page-queue.php`
- `admin/views-page-settings.php`
- `assets/admin.js`
- `assets/admin.css`
- `includes/class-plugin.php`

## 11) Plugin File Inventory
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

## 12) UI Responsiveness Recommendations (Settings + Queue)

Goal: improve perceived speed and responsiveness of admin workflows without requiring major visual redesign.

### Recommended improvements
1. **Optimistic row updates**
- For queue row actions (`Process`, `Add to Queue`, approve/reject/skip), update visible row state immediately and roll back only if AJAX fails.

2. **Non-blocking feedback**
- Replace blocking `window.alert()` usage with inline messages/toasts.
- Keep action context local to the row or panel where the action happened.

3. **Consistent loading states**
- Disable only the clicked button while a request is in-flight.
- Swap button text to loading state (for example: `Processing...`, `Loading...`) and restore on completion.

4. **Avoid full page redirects where possible**
- Prefer AJAX updates and notices rendered in-page over redirect-based feedback loops, especially on queue actions.

5. **Live queue freshness**
- Poll lightweight status counts (`queued`, `processing`, `failed`) and refresh badges/summary text without reload.

6. **Preload next chunk**
- After initial queue render, fetch next page payload in background so `View more images` feels instant.

7. **Render-performance guardrails**
- Keep row updates incremental and scoped.
- Avoid repeated large HTML replacements in the table body when only one row changed.

8. **Long-run progress clarity**
- For `Process Queue Now`, continue to show progress with processed totals and clear end-state messaging.

### Desktop UI impact expectations
- Most recommendations are **behavioral only** and should not materially change desktop layout.
- Visible desktop changes should be limited to:
  - loading states/spinners/text on action controls,
  - inline success/error messages or toasts,
  - optional sticky controls if later implemented.
- If implemented carefully, desktop width presentation should remain visually consistent while feeling faster.
