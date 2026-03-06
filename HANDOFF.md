# Dynamic Alt Tags - Project Handoff (Current)

## 1) Project Snapshot
Dynamic Alt Tags is a WordPress plugin that generates image alt text through a Cloudflare Worker, manages a queue/history workflow, and supports per-image retrieval from Attachment Details.

Plugin path:
- `/Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags`

Repository:
- `https://github.com/ericsatzman/dynamic-alt-tags.git`
- Branch: `main`
- Plugin directory is the git root.

Current git state:
- Clean working tree.
- `main` synced with `origin/main`.

Latest commit:
- `67e9597` - Refresh handoff document for current project state

Recent commits (newest first):
1. `67e9597` Refresh handoff document for current project state
2. `666ef16` Refine plugin access controls and settings UI
3. `3e99eea` Add role-based queue access and admin-only settings access
4. `5877e34` Improve iPhone queue responsiveness with stacked mobile rows
5. `e58b1a0` Add mobile/tablet responsive CSS without desktop changes
6. `1a64c53` Refine attachment retrieve behavior for finalized and active states
7. `dbeb6d0` Restore attachment retrieve button to bottom placement
8. `1fa16f9` Update history columns and make thumbnails clickable
9. `c1fe55e` Reorder row actions to place Skip before retrieve
10. `cac5ac2` Add composer license to satisfy strict validation
11. `3b75cb9` Add title sync option handling in processor
12. `171a1f5` Add auto-apply alt text option for new uploads

## 2) Current Menus and Navigation
### Settings menu (left admin)
- Menu label: `Dynamic Alt Tags`
- Page slug: `ai-alt-text-settings`
- Page title (H1): `Dynamic Alt Tags Settings`

### Media menu (left admin)
- Menu label: `Dynamic Alt Tags`
- Page slug: `ai-alt-text-queue`
- Queue page title (H1): `Dynamic Alt Tags`

## 3) Access Model (Important)
Access is now split by page type:

### Settings page access
- Administrator only.
- Non-admin roles cannot view Settings page even if checked in role list.

### Queue page access (Media > Dynamic Alt Tags)
- Administrator always has access.
- Additional roles can be granted queue access via Settings role checkboxes.

### Where this is enforced
- Menu visibility and admin-post/wp-ajax handlers: `includes/class-admin.php`
- Attachment Details retrieve controls and upload-action AJAX: `includes/class-plugin.php`
- Central access methods:
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

### Removed field
- `Cloudflare Account ID` removed from settings UI.

### Access Control section
- Separate section at bottom: `Access Control`
- Role checkboxes are sorted alphabetically by display name.
- Description clarifies:
  - Administrator always full access
  - Selected roles get queue page access under Media

### Save notice behavior
- Settings save alert shows once at top as expected.
- Duplicate custom “Settings saved” block was removed.
- JS no longer strips settings-page notices.

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

### Row buttons and labels
- Active row buttons include: `Approve`, `Skip Image`, `Retrieve Alt Text`, `View Image`
- Row `Reject` button removed (bulk actions still include `Reject`).
- Top page process button text is `Retrieve Alt Text`.

### Thumbnail behavior
- Thumbnails are clickable and open the image URL in a new tab (same behavior as `View Image`).

### Responsiveness
- Mobile/tablet (`max-width: 782px`) has responsive overrides in `assets/admin.css`.
- Queue rows become stacked cards on mobile to improve iPhone usability.
- Desktop styles remain unchanged by using only mobile/tablet media queries.

## 6) Attachment Details (Media Modal) - Current Behavior
Implemented in `includes/class-plugin.php` and `assets/admin.js`.

### UI
- Old “Dynamic Alt Tags Review” block with dropdown/custom/reject/skip was removed.
- A single `Retrieve Alt Text` button is shown in the plugin field area (bottom placement).

### Retrieval flow
- Button calls upload action AJAX with `review_action=generate`.
- Logic in `apply_review_action()`:
  - If no row exists: enqueue + process
  - If status is `generated`: directly apply suggested alt
  - If status is `processing`: returns “try again” message
  - If status is finalized (`approved/rejected/skipped`): does not overwrite history; instructs user to requeue from Queue page

### Alt/title sync behavior
- Wherever alt text is applied, title sync is conditional by setting:
  - `sync_title_from_alt` (default on)

## 7) Upload/New Image Behavior
### New uploads
- Option: `auto_apply_new_uploads` (default off)
- If enabled, newly uploaded image suggestion is auto-approved/applied after generation.

## 8) Provider/Runtime Notes
- Cloudflare Worker URL in use historically: `https://alt-text-generator.webprod.workers.dev/`
- URL mode may fail for local/private URLs (e.g., `sandbox.local`)
- Direct upload mode is default/recommended; URL mode is optional
- Provider timeout increased to 90 seconds
- Error messaging improved to include more context where available

## 9) CI / Quality Tooling
### GitHub Actions
- Workflow: PHPCS + Composer audit
- Important prior fix: composer validate strict required `license` in `composer.json`

### Local checks
- PHPCS command:
  - `composer -d /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags phpcs`

## 10) Key Files (Start Here)
- `dynamic-alt-tags.php`
- `includes/class-plugin.php`
- `includes/class-admin.php`
- `includes/class-settings.php`
- `includes/class-processor.php`
- `includes/class-queue-repo.php`
- `admin/views-page-settings.php`
- `admin/views-page-queue.php`
- `assets/admin.js`
- `assets/admin.css`
- `readme.txt`

## 11) Known Constraints / Open Risks
1. Queue mobile UX is improved but still table-derived HTML; consider future semantic card markup server-side if needed.
2. Attachment Details button placement was intentionally reverted to bottom after multiple layout experiments.
3. Requeue for finalized images is intentionally restricted from Attachment Details; must be done from Queue page.
4. Access model now depends on role names (not capabilities); if custom role strategy changes, review `class-settings.php` methods.

## 12) Suggested Next Steps
1. Add automated tests (or WP integration smoke tests) for role-based access split:
   - admin-only settings
   - queue access for selected roles
2. Add a small Settings help box explaining why finalized items must be requeued from Queue page.
3. Optionally add an explicit “Requeue from Attachment Details” action if desired product-wise.
4. Update changelog in `readme.txt` with versioned entries reflecting current features.

## 13) Resume Checklist for New Codex Window
1. Confirm status:
- `git -C /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags status -sb`

2. Validate coding standards:
- `composer -d /Users/local-esatzman/Desktop/Sites/sandbox/app/public/wp-content/plugins/dynamic-alt-tags phpcs`

3. Validate core flows in WP admin:
- Settings page visible only for administrators
- Queue page visible for admin + checked roles
- Settings save shows single success notice
- Queue mobile view on iPhone width
- Attachment Details Retrieve Alt Text behavior

4. If access bugs appear:
- Start with `includes/class-settings.php` access methods, then `includes/class-admin.php` + `includes/class-plugin.php` callers.
