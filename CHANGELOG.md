# TFM Custom Functions — Changelog

Running record of all work done on the plugin. Newest first.

## 3.19.1 — remove absorbed standalones that were already deactivated
- **Fixed:** the file cleanup only removed absorbed standalones that were *active* at update time (via the handover). A copy that had already been **deactivated** before the update — commonly TFM Cookie Consent — was left on disk. Cleanup now removes any **installed-but-inactive** copy of an absorbed plugin (Press Release Manager, TFM Cookie Consent), identified by folder/file name or declared plugin name. It stays conservative: it never deletes an active plugin, uses direct filesystem access only (defers otherwise, never prompting for credentials), and is gated by a plugins-directory signature so it isn't a per-request cost.

## 3.19.0 — begin retiring the custom-scripts feature
_Phase 1 of sunsetting the plugin's custom head/footer scripts in favor of Elementor's Custom Code area. Nothing breaks — existing code keeps running; new input is frozen; a migration checklist is added._
- **Custom head/footer scripts are now frozen.** Existing scripts still render exactly as before, but the fields are **read-only** — no new or edited code can be saved. This is enforced **server-side** (the save handler only ever keeps the current value or clears it), not just in the UI, so it can't be bypassed by posting directly. The tab shows a "Deprecated" notice pointing to Elementor &rarr; Custom Code.
- **Removal is allowed.** Each field has a **Remove** checkbox so a site's code can be cleared once it's been migrated.
- **Fleet migration checklist.** The heartbeat now reports whether a site still has custom scripts and their total size — **metadata only; the code itself never leaves the site** (it can contain secrets). The dashboard shows a **"Custom code"** column, a count of sites still to migrate, and a "Needs migration" filter, so the rollout can be tracked to zero. Once no site reports custom code, the feature can be removed entirely (a later release).

## 3.18.2 — hands-off updates & absorbed-plugin file cleanup
- **Background auto-updates for the plugin.** TFM Custom Functions now updates itself on WordPress's own schedule, so a published release rolls out across the fleet within hours with no per-site action. Pull-based (each site fetches from the TFM repo over HTTPS) — it adds no inbound endpoint or new attack surface. On by default; opt out on a specific site with `define('TFM_DISABLE_AUTO_UPDATE', true);` or the `tfm_enable_auto_update` filter.
- **Absorbed standalones are now fully removed, not just deactivated.** After the handover deactivates an absorbed standalone (Press Release Manager / TFM Cookie Consent), the plugin deletes its leftover files too. Conservative by design: it only ever deletes plugins TFM itself queued during handover and only once they're inactive, it never scans or guesses, it defers silently if the host filesystem isn't directly writable, and the removal is recorded in the activity log.

## 3.18.1 — robust absorbed-plugin handover
- **Fixed the absorbed-plugin handover missing some installs.** The deactivation of the now-absorbed standalones (Press Release Manager, TFM Cookie Consent) matched only one exact main-file name, so sites that packaged the plugin under a different folder/file name kept the standalone active (risking a class/CPT redeclaration and a confusing "still active" state). Matching is now loose: it normalizes folder/file names (case, spaces, underscores, `.php`) **and** falls back to matching the plugin's declared "Plugin Name" header — so it catches every packaging variant across the fleet, and also clears stale/ghost entries whose files are gone. The header scan is cached against the active-plugins list so it isn't a per-request cost. (Found during the staged 3.18.0 rollout on staging.)

## 3.18.0 — fleet alerting, heartbeat & health dashboard
- **ClickUp alerts for critical events.** The plugin sends critical activity-log events (plugin activate/deactivate/delete, role change, user delete, failed logins, permanent delete, core update) to a central ClickUp "Site Alerts" list via a TFM relay — non-blocking, throttled (1 per event+actor per 5 min). No credential lives on any site (the ClickUp token stays at the relay).
- **Heartbeat + fleet auto-discovery.** Each site checks in every 15 min (wp-cron) with its version/health info; the relay records it, so the fleet self-registers — no manually maintained site list.
- **Site-down + recovery alerts.** A relay cron (every 5 min) flags sites that stop checking in (no heartbeat for 45 min, confirmed by a direct request) → "site down" ClickUp task; a "recovered" task when they return. (Detecting downtime must be external — a down site can't report itself — so it watches for missing heartbeats.)
- **Fleet dashboard.** A relay page lists every site with its plugin/PHP/WP version, last-seen, and up/down status.
- The relay is a small Vercel service in its own repo (`TopFireMedia/tfm-alert-relay`). The plugin only needs the relay URL (baked-in default, overridable via `TFM_ALERT_RELAY_URL`).

## 3.17.0 — absorb the team plugins (Press Releases + Cookie Consent)
- **Merged two standalone TFM plugins into TFM Custom Functions** (fewer plugins to maintain per site):
  - **Press Release Manager** → `includes/press-releases.php` + the "Press Release Grid" Elementor widget. Preserves the `press_release` CPT, its ACF/SCF fields, and the widget name (`press_release_grid`), so existing press releases and Elementor pages are unchanged.
  - **TFM Cookie Consent** → `includes/cookie-consent/` (classes + assets) + a `includes/cookie-consent.php` bootstrap. Preserves the `tfm_cookie_consent_settings` option and class names, so existing configuration is unchanged. Removed debug `error_log` noise.
- **Safe fleet handover:** when TFM updates, if a now-absorbed standalone is still active, TFM stays dormant that request and automatically deactivates it (fleet-wide, incl. sites with no login access), then takes over on the next load — no class-redeclaration fatal. Verified on the local site clone with both standalones active.
- **Secure Custom Fields is intentionally NOT bundled** — it's a large (25 MB) third-party plugin, so it stays a standalone dependency; the absorbed plugins use it for their fields.

## 3.16.0 — modularize the monolith
- Split the 3,674-line `topfiremedia.php` into a thin bootstrap (~285 lines) plus focused includes: `settings.php`, `shortcodes.php`, `sitemap.php`, `frontend-scripts.php`, `svg-uploads.php`, `news.php`, `revisions.php`, `upgrades.php`, `admin.php`, `optimizations.php`, `login-branding.php`. Code moved verbatim (no logic change); verified on a full local clone (site renders identically, shortcodes/logging/admin all work, no fatals, no duplicate functions).
- **Restored the `disable_emojis` feature.** It had been stored as a single commented-out one-line blob, so the setting did nothing; it's now proper code in `optimizations.php` and actually strips the WordPress emoji scripts/styles when the setting is enabled (verified live).


## 3.15.0 — security, efficiency & phone-formatting batch
_Batched release (tested together to avoid many separate fleet updates; validated on a full local site clone)._

**Security**
- **Fixed stored-XSS via SVG uploads.** SVG mime restricted to `unfiltered_html` users; new `TFM_SVG_Sanitizer` strips `<script>`, event handlers, `<foreignObject>`, external entities (XXE), and script/data URIs from every uploaded SVG (payload-tested); unsafe files rejected. Added `wp_check_filetype_and_ext` handling so legitimate SVGs still upload.
- **Removed the `[financial_test]` debug shortcode**, which printed the franchise financials array on the front end.
- **Login-logo URL now safely quoted** in its CSS `url()` context.

**Phone formatting**
- The formatter now handles **every** `input[type="tel"]`, not just recognized form builders (Elementor Pro / Gravity / CF7).
- A one-time upgrade **auto-removes the redundant manual "format all tel inputs" script** across all installs — surgical (only that script block; other custom scripts preserved), recorded in the activity log, opt-out via `define('TFM_KEEP_LEGACY_PHONE_SCRIPTS', true)`.

**Performance / efficiency**
- Font Awesome + phone-formatter now toggleable (default on); deferral filter registered only when enabled; `window.tfmPhoneNumber` printed only when a phone is set (_note: `undefined` instead of a placeholder on unconfigured sites_); sitemap debug-page cache fix + query tuning; updater uses the version constant; debounced `video-defer.js` / `phone-formatter.js` observers; dead-code cleanup.

## 3.14.2 — Activity-logging rebuild (accountability)
- **Rebuilt the activity log to reliably record who did what.**
  - Captures the real acting user on every event; non-interactive actions labeled `cron`/`wp-cli`/`rest`/`unauthenticated` instead of a blank user. Login events attributed to the logging-in user.
  - Full event coverage: logins, failed logins, logouts, registrations, profile/role changes, user deletion; publish/edit/status/trash/delete across all post types (autosaves/revisions skipped, changed fields recorded); **Elementor** edits; media; comments; plugin activate/deactivate/delete; theme switch; plugin/theme/core updates; security-relevant option changes (allowlist).
  - Real severity filtering via a working Log Level setting (All / Important / Critical); severity stored per entry.
  - Removed the log-noise firehose; on by default with a one-time upgrade that enables it on existing installs.
  - Viewer shows newest activity first (was capped at the oldest 500 entries), never renders a blank actor, and adds a Log Level selector.
  - Reviewed (independent adversarial pass), `php -l` clean, staging-tested.

## 3.13.1 — Update-channel verification
- Corrected code comments now that the default update source is a public repo. Used as the differential test that proved sites pull updates from the TFM-owned repo.

## 3.13.0 — Security + update-source migration
- **Removed a hardcoded GitHub token** from the updater (was present in source and git history); the token is now read from a `TFM_GITHUB_TOKEN` wp-config constant or the settings option, never hardcoded.
- **Removed `debug_update_checker()`**, which made a synchronous, uncached GitHub API request on every wp-admin page load and used none of the results.
- **Added a per-request cache to `tfm_load_settings()`** (was rebuilding a ~55-key defaults array on ~45 calls per request).
- **Made the update source configurable** — repo URL and branch overridable via `TFM_UPDATE_REPO`/`TFM_UPDATE_BRANCH` constants or filters.
- **Repointed the default update source** to the Top Fire Media-owned public repo (`TopFireMedia/tfm-plugin`); delivered through the previous repo as the migration release so all sites move onto the TFM channel automatically.

## 3.12.11 — Baseline
- Starting point before this engagement.
