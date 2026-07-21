# TFM Custom Functions — Changelog

Running record of all work done on the plugin. Newest first.

## Unreleased — batched fixes (holding to ship together)
_These are staged on the `release-batch` branch and intentionally not released individually, to avoid many separate fleet updates. Includes the SVG hardening (3.14.3) below, plus:_
- **Removed the `[financial_test]` debug shortcode**, which printed the franchise financials array (`print_r`) on the front end of any page/post where it was placed — an information-disclosure / leftover debug surface.
- **Login-logo URL now safely quoted in its CSS `url()` context** (was output unquoted; `esc_url` is for HTML/URL, not CSS). Admin-controlled, so low risk, but correct now.

## 3.14.3 — SVG upload hardening (security)
- **Fixed stored-XSS via SVG uploads.** SVG uploads (`enable_svg_uploads`) previously accepted files with no sanitization.
  - SVG mime type is now only allowed for users with `unfiltered_html` (admins/super-admins), so lower-privilege users can't upload a scripted SVG that runs in an admin's browser.
  - New `TFM_SVG_Sanitizer` strips `<script>`, event handlers, `<foreignObject>`, external entities (XXE), and script/data URIs from every uploaded SVG; unsafe files are rejected. Sanitization runs on `wp_handle_upload_prefilter` (by extension) and `wp_handle_upload` (by type).
  - Added `wp_check_filetype_and_ext` handling so legitimate SVGs upload correctly.

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
