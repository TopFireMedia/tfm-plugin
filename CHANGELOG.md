# TFM Custom Functions — Changelog

Running record of all work done on the plugin. Newest first.

---

## Scheduled removals / technical debt

Standing list. Remove items from here when they ship.

- _(Empty — all current items shipped. The legacy cookie-consent module was removed in 3.33.0; the custom block-pattern label shipped in 3.32.0; the two stuck sites were updated 2026-07-31 via an SSH sweep — root cause was wp-cron lag on low-traffic sites, no blocking constants.)_

**Open observation (not code debt):** auto-update depends on WordPress cron, which depends on site traffic — so very low-traffic sites chronically lag. The relay's warm-ping helps, and a periodic SSH sweep catches the rest. A real server-side cron for wp-cron across the fleet would make this fully hands-off.

---

## 3.34.0 — staged / canary auto-updates

- **Auto-update now honors an optional fleet rollout policy** served by the relay (`/api/rollout`), so a release doesn't have to hit all ~78 sites at once. Pin the fleet at a version (`hold_at`), update a **canary** site or two by hand and verify on the dashboard, then lift the hold to release to everyone. Canary hosts always take the latest. It **fails open** — if the relay is unreachable, normal auto-update proceeds, so a relay outage can never freeze the fleet's updates. Policy is cached ~30 min per site; overridable via `TFM_ROLLOUT_URL`.

## 3.33.0 — remove the legacy cookie-consent module

- **Deleted the superseded cookie-consent module** (`includes/cookie-consent.php` + `includes/cookie-consent/`, 11 files, ~4,600 lines). TFM Tracking Consent (3.26.0) replaced it with real prior-consent blocking, Consent Mode v2, and consent receipts. Removal was **gated on the fleet reporting zero sites still using the legacy system** — confirmed via the heartbeat (`cookie_consent.system`): 0 legacy across all reporting sites. Also removed the now-dead pieces: the legacy branch in `tfm_heartbeat_cookie_consent_state()`, the `tfm_tracking_consent_conflict_notice()` admin warning, and the one-time `tfm_cookie_consent_default_off()` migration (every site is long past it). The standalone-plugin file-cleanup for the external "TFM Cookie Consent" plugin is unaffected and stays.

## 3.32.0 — custom cookie block-patterns can carry a display label

- **Custom block patterns now accept an optional label** so custom-blocked vendors appear in the `[tfm_cookie_declaration]` table. Format is now `pattern|category` **or** `pattern|category|Label` — the label is what shows in the declaration (e.g. `widget.example.com|advertising|Acme Widget`). Previously a site relying on custom rules published an incomplete declaration because custom patterns had no display name. Existing two-field lines keep working unchanged; the blocking map still uses only pattern + category.

## 3.31.0 — report update health to the fleet dashboard

- **The heartbeat now reports why a site might be stuck behind:** whether auto-update is enabled, and whether a GitHub token is set (a leftover token 401s against the public repo and silently blocks update checks — the likely cause of a site being stranded on an old version). The fleet dashboard gains an **"Updates"** column (Auto / Auto off / Token set / —), an **"Update issues"** count, and a matching filter, so a stranded site is flagged at a glance instead of found by accident.

## 3.30.0 — paginate the full activity-log history

- **The activity log viewer now pages through all recorded history**, not just the most recent window. Added server-side **Newer / Older** navigation with a running total ("Showing X–Y of Z events"), backed by a new `count_logs()` on the logger (streams line-by-line, low memory). Within each window, DataTables still provides its own paging, search, filter, and sort. Window size stays filterable via `tfm_activity_log_view_limit` (default 1000). Replaces the old fixed "most recent 500/1000 only" behavior.

## 3.29.0 — purge the existing failed-login backlog from the activity log

- **One-time cleanup strips the existing `user_login_failed` entries from the activity-log files.** 3.28.0 stopped writing new ones, but the historical spam still filled the files and dominated the recent view until it aged out. This rewrites each monthly log file keeping only the real events, so the log shows meaningful accountability history immediately. Only touches a file that actually contains failed-login entries; other entries and the file format are preserved. Combined with the 500 → 1000 viewer limit from 3.28.0, far more real activity is now visible at once.

## 3.28.0 — stop logging failed logins (they were flooding the activity log)

- **Failed logins are no longer written to the activity log.** They're constant automated bot traffic on every WordPress site, and because the log viewer shows a capped window of recent entries, failed-login spam was crowding out the real accountability events the log exists for — leaving only ~a day of useful history. Removing them restores days/weeks of real activity. (They were already dropped from ClickUp alerts in 3.25.0; this does the same for the on-site log.) Re-enable per-site with `add_filter('tfm_log_failed_logins', '__return_true')`. Failed logins remain the job of a dedicated security plugin (e.g. Wordfence).
- **Activity-log viewer raised from 500 to 1000 entries** (filterable via `tfm_activity_log_view_limit`), for more history at a glance now that the noise is gone.

## 3.27.0 — heartbeat every 20 minutes instead of 10

The relay's datastore (Upstash) bills per command and has a 500,000/month
ceiling on its plan. Fleet usage reached 885,000 and Upstash began rejecting
requests, which took the dashboard and the down-alerting offline. Across the
fleet the heartbeat was the single largest consumer.

- **Heartbeat interval 10 min -> 20 min.** Halves the plugin's share of the
  budget. Freshness costs little: up/down is decided by the relay's direct ping,
  not by the heartbeat, and a site is only declared down after 45 minutes
  without contact — so a 20-minute heartbeat still gives two chances inside that
  window. This reverses the 3.19.2 change, which lowered it to 10 minutes for
  fresher "last seen" before the per-command cost was understood.
- **Interval is now filterable** via `tfm_heartbeat_interval` (seconds), clamped
  between 5 and 45 minutes so a site can't be set to hammer the relay or drift
  past the down threshold.
- The schedule was renamed `tfm_ten_minutes` -> `tfm_heartbeat`; the old
  definition is retained so sites still holding an event on it stay valid until
  the migration moves them across on the next `init`.

Relay-side changes made alongside this (see tfm-alert-relay): the dashboard now
fetches all sites in one `MGET` rather than one `GET` each and refreshes every
5 minutes rather than 60 seconds, the heartbeat endpoint only writes set
membership on first contact, and the monitor polls every 5 minutes instead of 2.
Combined projection: ~2,082K commands/month down to ~214K.

## 3.26.1 — "Do Not Sell or Share" is now an actual opt-out

- **`[tfm_do_not_sell]` opts the visitor out in one click.** It previously emitted the same `data-tfm-tc-action="preferences"` as `[tfm_consent_link]`, so both links merely opened the preferences panel — identical behaviour with a different label. The `tfm-tc-dns` class it added had no JavaScript and no CSS behind it, and the docblock's claim that it "opens preferences with advertising highlighted" was never implemented. An opt-out link that requires opening a panel, finding a toggle and saving is more steps than it should be. It now withdraws the Advertising and Personalization categories directly — the ones that constitute a sale or share — and preserves whatever the visitor already chose for Analytics and Functional, which are business purposes rather than a sale. Pass `mode="preferences"` for the old behaviour.
- **Added a confirmation toast.** The link usually lives in a footer with no panel open, and `decide()` ends by hiding the banner, so clicking it previously produced no visible feedback at all. The confirmation is `role="status"` with `aria-live="polite"`, and honours `prefers-reduced-motion`.
- **`do_not_sell` added to the consent-receipt allow-list.** The REST endpoint validates the action against a fixed list, so opt-out receipts would have been rejected with a 400 and lost. The `action` column is `VARCHAR(20)`, so the value fits.

## 3.26.0 — absorb TFM Tracking Consent; fix the old consent module

Started as an enforcement layer bolted onto the existing cookie-consent module. On finding that a separate standalone plugin — **TFM Tracking Consent 1.0.0**, live on ivykidsfranchise.com — already solved the same problem better, that work was discarded and the standalone absorbed instead. Comparison notes are at the bottom.

### Absorbed: TFM Tracking Consent

Now lives under `includes/tracking-consent/`, loaded by `includes/tracking-consent.php`, using the same `tfm_handover_absorbed_plugin()` handover as cookie-consent and press-releases. Option name (`tfm_tc_settings`), class names and text domain are preserved, so a site running the standalone keeps its configuration and simply stops loading the separate plugin.

What it does that the old module never did:

- **Three-layer prior-consent blocking.** Server-side tag rewrite to `type="text/plain"` with `src` moved to `data-tfm-tc-src`, a `document.createElement` gate that catches trackers injected at runtime by other scripts, and a MutationObserver fallback. The `createElement` gate matters on these sites: a server-side rewrite alone cannot stop an Elementor video widget building a YouTube player after page load.
- **Cache-safe by construction.** Blocking is identical for every visitor and unblocking happens in the browser, so page caches stay correct. Verified live on ivykidsfranchise.com behind NitroPack.
- **Google Consent Mode v2** with all six storage signals plus `wait_for_update`, reading the consent cookie so returning visitors get correct defaults without a denied-then-granted flicker. GTM can run strict (container never loads pre-consent) or in Consent Mode.
- **26-service built-in registry** plus per-site `needle|category` patterns for vendors served from rotating hostnames.
- **Consent receipts** — action, categories, consent version, salted one-way IP hash (raw IPs never stored), truncated user agent. Rate-limited REST endpoint, admin viewer, CSV export.
- **Shortcodes**: `[tfm_consent_button]`, `[tfm_consent_link]`, `[tfm_do_not_sell]` (CCPA/CPRA), `[tfm_cookie_declaration]` (auto-generated category/service table for privacy policies).
- **Global Privacy Control** honoured as an opt-out; five categories; accessible dialog with focus management; Elementor Pro template support.

Ported in from the discarded work, being the only two things it did better:

- **`url_passthrough` is now a setting.** It was hardcoded `false`. When on, ad click IDs survive between pages while storage is denied, so conversion measurement degrades instead of vanishing. Off by default — it rewrites outbound links.
- **Optional region scoping** for the denied default (`GB,DE,FR`). Empty means deny worldwide, which stays the default because US state laws expect it.

### Fixed in the old cookie-consent module

It is now deprecated in favour of the above, but two real bugs are fixed since 30 sites still hold its option:

- **Fatal JavaScript syntax error.** `add_cookie_blocking_script_early()` opened an `else {` that was never closed, so the whole inline blocking script failed to parse with "Unexpected end of input" and the `document.cookie` override was **never installed on any site** since it shipped. Verified in a headless browser against a live site: `getOwnPropertyDescriptor(document,'cookie')` returned undefined and no TFM blocking log appeared.
- **Settings silently wiped on save.** `sanitize_settings()` rebuilt the array from scratch and returned only keys it knew about, destroying anything else on every save. Same class of bug as the 3.12.11 News regression. Now starts from the stored option, still forcing checkboxes to `false` when absent since unchecked boxes are never POSTed.
- Removed two `console.log` calls that ran on every page load regardless of debug mode.

An admin notice warns if both consent systems are enabled at once.

### Fleet visibility

- **Heartbeat reports consent state** in a `cookie_consent` block: `system` (`tracking`/`cookie`/`none`), `banner`, `consent_mode`, `prior_blocking`, `block_iframes`, `respect_gpc`, `receipts`, a `patterns` count, and a derived `enforcing`. Tracking Consent wins when enabled, otherwise the legacy module is reported, so sites mid-migration read accurately. Booleans and counts only — never banner copy or pattern hostnames, matching the `custom_scripts` rule. The relay recomputes `enforcing` rather than trusting it.
- The fleet monitor gained a Consent column, a "Consent not enforced" stat and filter (see tfm-alert-relay).

### Upgrade behaviour

**This release changes nothing on any live site.** Tracking Consent ships disabled: the standalone defaulted to `enabled => 1` because activating it was itself the opt-in, but absorbed it arrives everywhere at once, so it is forced off unless the site was migrated from an active standalone (provenance in `tfm_tc_activated`). There is no migration routine for the old module either — with its enforcement keys absent, every check already reads as off.

The one real behaviour change is on sites that already had the **old** banner enabled: its cookie blocking will start working where it previously failed silently. Currently that is zero sites on the VPS — a wp-cli sweep of all 33 WordPress installs found the old banner off everywhere.

## 3.25.0 — stop alerting failed logins to ClickUp
- **Failed logins no longer create ClickUp alerts.** They're constant automated bot traffic on every WordPress site, so the volume was overwhelming and useless as a signal. Removed `user_login_failed` from the alerted actions (still recorded in the activity log; re-add per-site via the `tfm_alert_actions` filter if ever needed). The relay also mutes it as a backstop, so the noise stopped fleet-wide immediately.

## 3.24.0 — force dev sites to noindex
- **Development sites can never be indexed.** Any `tfmstaging.com` subdomain (or the apex) now has search-engine indexing forced **off** and locked — WordPress behaves as noindex regardless of the stored setting, and Settings → Reading can't re-enable it. The stored value is normalized to 0 too (so Plesk, the settings screen, and the heartbeat all agree), with an explanatory notice on the Reading screen. Live sites are unaffected. Detection is overridable via the `tfm_is_dev_site` filter.

## 3.23.0 — report search-engine indexing state to the fleet dashboard
- **The heartbeat now reports whether search-engine indexing is on** (WordPress Settings → Reading → "Discourage search engines", i.e. the `blog_public` option — the same thing Plesk shows). The fleet dashboard gains an **"Index"** column (On / Off / — for not-yet-reporting), a **count of sites with indexing off**, and an **"Indexing off" filter** — so a live site accidentally left non-indexable (noindex) is easy to spot. Staging sites are expected to show Off; the value is for eyeballing production sites.

## 3.22.0 — SCF fleet visibility, ping-based up/down, name-display fix
- **Heartbeat now reports whether Secure Custom Fields / ACF is active**, so the fleet dashboard can show an "SCF active" column, count, and filter — a checklist of where SCF can still be removed (now that Press Releases no longer need it).
- **Site name is sent decoded**, fixing names with apostrophes or ampersands that showed as raw HTML entities (e.g. `&#039;`) on the dashboard. The dashboard also decodes defensively, so existing entries display correctly right away.
- **Up/down is now decided by directly pinging each site** (relay change), not by whether the site phoned home. A ping is authoritative and doesn't depend on the site getting traffic, so status is far more reliable; a site is only flagged down after consecutive failed pings, and "last seen" reflects the last successful contact.

## 3.21.0 — press releases no longer require Secure Custom Fields
- **Made SCF/ACF optional for Press Releases** (it was the *only* feature in the plugin using it, and only for four simple fields: external URL, source name, release date, featured flag).
  - The Elementor "Press Release Grid" widget now reads those fields with `get_post_meta()` (ACF/SCF already stored them there under the same keys), so it renders **with or without SCF installed**.
  - Added a **native meta box** for editing the fields that appears **only when SCF is not active** — when SCF is present, its field group is used instead, so there's no duplicate box.
  - Data is preserved exactly: same meta keys, dates stored in ACF's `Ymd` format, so existing press releases, sort order, and templates are unchanged — and **SCF can now be uninstalled fleet-wide** without breaking press releases. No full SCF rebuild.

## 3.20.0 — cookie consent inactive by default
- **The cookie-consent banner is now off by default.** Earlier builds seeded it **on** for every install, so sites that never used cookie consent got a banner. Now it's enabled only where the site actually used it: when the absorb handover deactivates an **active** standalone "TFM Cookie Consent", it records provenance and keeps the banner on; every other install defaults to off. A one-time migration turns the banner off on any site that was only auto-enabled by the old default (sites with provenance are preserved). It can still be enabled per-site from the plugin settings. _Rule: off by default, but if it was active in the old standalone plugin it stays active._

## 3.19.2 — fresher "last seen" on low-traffic sites
- **Heartbeat interval tightened to 10 minutes** (was 15), and existing sites are migrated off the old 15-minute schedule automatically.
- Paired with a relay change (the fleet monitor now nudges every site each run, every ~5 min, which wakes its wp-cron), so a site's "last seen" stays within roughly 15 minutes **even with zero visitor traffic** — previously an idle staging site could show "45+ min ago" because WordPress cron only runs on a page visit. Real-traffic sites were always fine; this fixes the idle case.

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
