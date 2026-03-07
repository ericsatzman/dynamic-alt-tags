# Dynamic Alt Tags - Project Handoff (Current)

## 1) Project Snapshot
Dynamic Alt Tags is a WordPress plugin that generates image alt text through a Cloudflare Worker, supports queue/history review workflows, and allows per-image generation from Media Attachment Details.

Plugin path:
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags`

Repository:
- `https://github.com/ericsatzman/dynamic-alt-tags.git`
- Branch: `main`
- Plugin directory is the git root.

Current git state:
- Working tree expected clean after latest push.
- `main` synced with `origin/main`.

Latest commit:
- `0dc114d` - Prevent false upload-action errors after successful media apply

Recent commits (newest first):
1. `0dc114d` Prevent false upload-action errors after successful media apply
2. `c4430de` Stabilize media grid sidebar alt/title field syncing
3. `c9607e0` Improve media-library generate apply reliability and title sync UI
4. `0c417ec` Refine queue refresh behavior and harden alt-text truncation
5. `7f40f76` Hide queue progress inline errors and rely on top alerts
6. `a5bea33` Add queue-page processing progress and update bulk actions
7. `f9bc653` Add top Refresh button to queue page actions
8. `cdd210a` Trim cut-off alt text to complete sentences
9. `4d71e2f` Clarify Worker URL and token instructions
10. `e60efd6` Remove separate user guide document

## 2) Current Menus and Navigation
### Settings menu (left admin)
- Menu label: `Dynamic Alt Tags`
- Page slug: `ai-alt-text-settings`
- Page title (H1): `Dynamic Alt Tags Settings`

### Media menu (left admin)
- Menu label: `Dynamic Alt Tags`
- Page slug: `ai-alt-text-queue`
- Queue page title (H1): `Dynamic Alt Tags`

## 3) Access and Security Model
Access split by page type:

### Settings page access
- Administrator only.

### Queue page access (Media > Dynamic Alt Tags)
- Administrator always has access.
- Additional roles can be granted queue access via Settings role checkboxes.

### Additional permission hardening
- Queue/media actions now also enforce per-attachment capability checks (`edit_post`) before modifying attachment metadata/title.
- Attachment upload-action AJAX validates capability + nonce.

### Where this is enforced
- Menu visibility and admin-post/wp-ajax handlers: `includes/class-admin.php`
- Attachment Details retrieve controls and upload-action AJAX: `includes/class-plugin.php`
- Queue processing/approval guards: `includes/class-processor.php`
- Access methods:
  - `WPAI_Alt_Text_Settings::current_user_can_access_settings()`
  - `WPAI_Alt_Text_Settings::current_user_can_access_queue()`
  - `WPAI_Alt_Text_Settings::current_user_is_administrator()`

## 4) Settings Page - Current Behavior
Settings are rendered by `includes/class-settings.php` and `admin/views-page-settings.php`.

### Current fields
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
- Roles Allowed To Access Dynamic Alt Tags

### Token behavior
- If Cloudflare API Token field is saved empty, stored token is cleared.

### Access Control section
- Separate section: `Access Control`
- Role checkboxes sorted alphabetically by display name.
- Description clarifies:
  - Administrator always full access
  - Selected roles get queue page access under Media

### Save notice behavior
- Settings save alert shows once at top.
- Duplicate custom “Settings saved” block removed.

## 5) Queue Page - Current Behavior
Queue page template: `admin/views-page-queue.php`

### Views/Tabs
- Active Queue
- History
- No Alt Images

### Active Queue columns
- Image
- Status
- Confidence
- Existing Alt
- Suggested Alt
- Actions

### History columns
- Image
- Status
- Confidence
- Alt Text
- Processed On (uses WP General Settings date/time format)
- Actions

### No Alt Images columns
- Image
- Alt Text
- Queue Status
- Actions

### Buttons and labels
- Active row buttons: `Approve`, `Skip Image`, `Generate Alt Text`, `View Image`
- Top process button text: `Generate Alt Text`
- Top refresh button (blue): `Refresh`

### Bulk actions
- Current bulk options include: `Generate Alt Text`, `Approve`, `Skip Image`
- `Reject` removed from bulk dropdowns.

### Progress UI
- Queue-level progress bar appears below “Total queue items” and above bulk actions.
- Used for:
  - Top `Generate Alt Text` processing
  - Bulk `Generate Alt Text` processing
- Inline top progress error text suppressed; errors surface in top alert notices.

### Refresh behavior
- `Refresh` uses a clean queue URL (keeps only page/view/status/paged) to prevent stale alert params from reappearing.

### Thumbnail behavior
- Thumbnails are clickable and open image URL in a new tab.

### Responsiveness
- Mobile/tablet (`max-width: 782px`) responsive overrides in `assets/admin.css`.
- Queue rows rendered as stacked card-like blocks on smaller screens.

## 6) Attachment Details (Media Modal) - Current Behavior
Implemented in `includes/class-plugin.php` and `assets/admin.js`.

### UI
- Single `Generate Alt Text` button shown in plugin field area.

### Retrieval flow (`review_action=generate`)
- If no row exists: enqueue + process
- If status `generated`: apply suggested alt directly
- If status `processing`: returns “try again” message
- If status `skipped`: auto-requeue + process
- If status `approved` or `rejected`: requires requeue from Queue page

### Reliability fixes for Media Library grid/right-sidebar
- Frontend now updates both:
  - DOM fields (Alt/Text and Title if sync enabled)
  - `wp.media` model state for selected attachment
- Reapplies updates after short delays to survive async sidebar re-renders.
- Prevents false “Unable to apply upload action” messages after successful apply.

### Alt/title sync behavior
- Wherever alt text is applied, title sync follows setting `sync_title_from_alt` (default on).

## 7) Alt Text Truncation Behavior
Implemented in `includes/class-alt-generator.php`.

- Normalization trims likely cut-off endings.
- Attempts to keep complete sentence output.
- Trims dangling connectors/partial clause tails (e.g., trailing “and a”).
- Appends terminal punctuation when needed.

## 8) Upload/New Image Behavior
### New uploads
- Option: `auto_apply_new_uploads` (default off)
- If enabled, newly uploaded image suggestion is auto-approved/applied.

## 9) Provider/Runtime Notes
- Cloudflare Worker URL historically used: `https://alt-text-generator.webprod.workers.dev/`
- URL mode can fail for local/private URLs (e.g., `sandbox.local`)
- Direct upload mode is default/recommended
- Provider timeout currently: 90 seconds
- Error messaging includes more context where available

## 10) Cron/Scheduling Notes
- Plugin schedules queue processing on `five_minutes` cadence.
- Existing non-`five_minutes` schedules are migrated on init.

## 11) CI / Quality Tooling
### GitHub Actions
- Workflow: PHPCS + Composer audit

### Local checks
- PHPCS command:
  - `composer -d /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags phpcs`

## 12) Documentation Status
- Primary WordPress readme: `readme.txt` (WordPress readme format)
- Styled Markdown readme: `README.md`
- `USER-GUIDE.md` removed

## 13) Key Files (Start Here)
- `dynamic-alt-tags.php`
- `includes/class-plugin.php`
- `includes/class-admin.php`
- `includes/class-settings.php`
- `includes/class-processor.php`
- `includes/class-queue-repo.php`
- `includes/class-alt-generator.php`
- `admin/views-page-settings.php`
- `admin/views-page-queue.php`
- `assets/admin.js`
- `assets/admin.css`
- `readme.txt`
- `README.md`

## 14) Known Constraints / Open Risks
1. Queue mobile UX is improved but still table-derived HTML; semantic card markup would be a deeper refactor.
2. Provider remains external dependency (availability/latency risk).
3. Queue access still role-list based in settings model (not full capability mapping strategy).
4. Media modal behavior depends on WP media-frame internals and can be sensitive to admin/plugin conflicts.

## 15) Optimization Roadmap (Potential Performance/Efficiency Improvements)
### Quick wins (1-3 days)
1. Add in-request caching for repeated option/row lookups.
2. Skip redundant processing paths earlier (`generated` guard, retry cooldown).
3. Add exponential backoff + retry cap for failed rows.
4. Add lightweight metrics logging (latency, success/failure counts).

### Near-term (1-2 weeks)
1. Bounded parallel queue processing (e.g., 3-8 jobs concurrently).
2. Circuit breaker when provider is degraded.
3. DB/query tuning + index validation for hot paths.
4. Smarter retry lifecycle using next-attempt eligibility.
5. Direct-upload payload optimization (optional pre-resize/compress).

### Strategic (2-6 weeks)
1. Batch provider endpoint support (multiple images/request).
2. Result dedup/cache by image hash.
3. Migrate heavy queue execution to Action Scheduler-style job runner.
4. Add operator metrics dashboard (drain rate, p95 latency, failure taxonomy).

### Suggested execution order
1. Backoff/cooldown guards.
2. Metrics instrumentation.
3. Parallelism + circuit breaker.
4. DB/index tuning.
5. Payload optimization.
6. Batch API + hash cache.
7. Async job runner migration.

## 16) Suggested Next Steps
1. Add integration tests for:
   - role/capability access split
   - queue progress workflows
   - media grid/right-sidebar apply reliability
2. Add provider health visibility in admin (status + recent failure trend).
3. Add changelog/version entries for post-0.1.0 improvements.

## 17) Resume Checklist for New Codex Window
1. Confirm status:
- `git -C /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags status -sb`

2. Validate coding standards:
- `composer -d /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags phpcs`

3. Validate core flows in WP admin:
- Settings page visible only for administrators
- Queue page visible for admin + checked roles
- Queue top + bulk `Generate Alt Text` progress bar behavior
- Attachment Details `Generate Alt Text` behavior in list and grid/sidebar media views
- Alert notices clear correctly after refresh

4. If access or apply bugs appear:
- Start with `includes/class-settings.php` access methods
- Then `includes/class-admin.php` + `includes/class-plugin.php`
- For grid/sidebar sync issues, inspect `assets/admin.js` media model sync helpers
