# Dynamic Alt Tags - Project Handoff

## 1) Project Overview
Dynamic Alt Tags is a WordPress plugin that queues image attachments, sends each image URL to a Cloudflare Worker, receives AI-generated alt-text suggestions, and lets admins review/apply/skip those suggestions.

Current plugin location:
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags`

GitHub repository:
- `https://github.com/ericsatzman/dynamic-alt-tags.git`
- Branch: `main`

Latest pushed commit at handoff:
- `9bd5e40` - Add View Image action to queue row actions

## 2) What Has Been Implemented

### Core plugin scaffold
- Main bootstrap file:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/dynamic-alt-tags.php`
- Activation/deactivation + queue table creation:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-activator.php`
- Main plugin wiring/hooks:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-plugin.php`

### Queue + processing
- Queue repository (CRUD + claim jobs + status transitions):
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-queue-repo.php`
- Processor (batch loop, provider call, quality checks, save statuses):
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-processor.php`
- Alt normalizer/quality checks:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-alt-generator.php`

### Provider integration
- Provider interface:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-provider-interface.php`
- Cloudflare provider implementation:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-provider-cloudflare.php`
- Request contract expected by plugin:
  - Sends POST JSON with `image_url`, `context`, and `rules`
  - Optional `Authorization: Bearer <cloudflare_token>`
- Response shape plugin accepts:
  - `{"alt_text":"...","confidence":0.0-1.0}` (also accepts `caption` instead of `alt_text`)

### Admin UI
- Settings page:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/admin/views-page-settings.php`
- Queue page:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/admin/views-page-queue.php`
- Admin controller:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-admin.php`
- Admin assets:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/assets/admin.js`
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/assets/admin.css`

### Added UX features completed recently
1. Token show/hide toggle on settings
2. Worker URL persistence hardening in sanitize logic
3. Queue bulk actions (WordPress-style):
   - Checkboxes + top/bottom bulk dropdowns + Apply
   - Bulk Approve / Reject / Skip decorative
4. Test Provider Connection button on Settings page
5. Inline note that Cloudflare Account ID is optional/currently unused
6. Queue row “View Image” button (opens full-size image in new tab)

## 3) Important Behavioral Notes

### Processing count nuance
The “Manual processing finished. X items processed.” number shows successful generations, not all attempted rows.
- Success increment happens only here:
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-processor.php:135`
- Failed/skipped rows are attempted but do not increment that counter.

### Batch size limits in code
- Settings cap: max `50`
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-settings.php:137`
- Claim jobs cap: max `50`
  - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-queue-repo.php:140`

### Queue statuses used
- `queued`, `processing`, `generated`, `approved`, `rejected`, `failed`, `skipped`

### “Skip decorative” behavior
- Writes empty alt text (`_wp_attachment_image_alt = ''`)
- Marks queue row as `skipped`
- Clears `_ai_alt_review_required`

## 4) Cloudflare Worker Setup Status / Context
The user configured a Worker named:
- `dynamic-alt-tags-worker`
- URL used: `https://dynamic-alt-tags-worker.eric-satzman.workers.dev/`

Key setup steps already discussed and mostly completed:
- Worker code replaced with POST-based handler
- Secret `PLUGIN_TOKEN` added (or rotated multiple times)
- Workers AI binding expected name: `AI`

Most frequent failure source observed in logs:
- `401 Unauthorized` from Worker (token mismatch)

Additional known trap:
- If WordPress image URLs are local/private (e.g., `sandbox.local`, localhost-only), Cloudflare Worker may fail fetching source images.

## 5) Cloudflare Account ID Field Purpose
Current plugin implementation does not use `cloudflare_account` in provider calls.
- Worker calls are direct to `worker_url`
- Token is optional bearer auth
- Field is retained for potential future use

Inline note appears in settings render:
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags/includes/class-settings.php`

## 6) Current Git / Workspace State

### Tracked repo scope
Repo was intentionally reduced to plugin-only tracked files under:
- `wp-content/plugins/dynamic-alt-tags/`

### Important local workspace caveat
In this WordPress directory there are many untracked core/plugin/theme files (local environment files). This is expected and should generally be ignored when committing to this repo.

At handoff, `git status --short` showed one main tracked change set already pushed (latest commit `9bd5e40`).

## 7) File Inventory (plugin)
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

## 8) How to Resume in a New Codex Context (Recommended Sequence)
1. Confirm branch and latest commit:
   - `git -C /Users/local-esatzman/Desktop/Sites/sandbox/app/public log --oneline -n 5`
2. Focus only on plugin tracked files under:
   - `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags`
3. Validate syntax after any edit:
   - `find /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags -name '*.php' -print0 | xargs -0 -n1 php -l`
4. Re-test provider:
   - WordPress admin -> Media -> Dynamic Alt Tags Settings -> Test Provider Connection
5. Re-run queue processing and inspect queue statuses.
6. If failures persist, inspect Cloudflare logs for POST `/` entries only.

## 9) Known Open Improvements / Next Tasks
High-priority
1. Improve process result reporting:
   - Show attempted/succeeded/failed/skipped counts separately.
2. Better error visibility in queue UI:
   - Display `error_code`/`error_message` column for failed rows.
3. Retry policy hardening:
   - Add max attempts and backoff behavior.

Medium-priority
4. Add WP-CLI command for processing queue in larger runs.
5. Improve security of token handling at rest (currently plain option storage).
6. Add tests (unit/integration for queue actions and settings handlers).

Optional UX
7. Add modal/lightbox preview instead of new-tab image view.
8. Add status filters and bulk-select by status.

## 10) Cloudflare Cost/Limit Guidance Already Provided to User
- Workers AI free: 10,000 neurons/day (UTC reset)
- Workers free plan request limits also apply
- Approximate costs discussed with caveats about paid plan minimums

## 11) Quick Troubleshooting Checklist
If queue shows failed rows with confidence 0.00:
1. Test provider connection from settings page.
2. Verify Worker URL exact match.
3. Verify token exact match with `PLUGIN_TOKEN` (rotate if unsure).
4. Confirm AI binding exists and named exactly `AI`.
5. Confirm images are publicly reachable by Worker.
6. Check Cloudflare logs for POST entries and response status.

## 12) Commits of Interest (for traceability)
- `9bd5e40` Add View Image action to queue row actions
- `512f883` Add queue bulk actions and provider connection test
- `ba88c80` Restrict repository contents to dynamic-alt-tags plugin only

