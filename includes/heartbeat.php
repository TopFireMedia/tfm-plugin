<?php
/**
 * TFM Heartbeat
 *
 * Each site "phones home" to the TFM relay on a schedule with its version/health
 * info. The relay records it (auto-discovering the fleet — no manual site list)
 * and its monitor watches for sites that stop checking in (= down). This also
 * powers the fleet version/health view.
 *
 * The heartbeat endpoint is derived from the alert relay URL (same host,
 * /api/heartbeat); override with TFM_HEARTBEAT_URL or the 'tfm_heartbeat_url' filter.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The heartbeat interval, overridable per site via the 'tfm_heartbeat_interval'
 * filter (seconds). 20 minutes: the relay's datastore bills per command, and
 * across the fleet the heartbeat is by far the largest consumer. Freshness costs
 * little here because up/down is decided by the monitor's direct ping, not by
 * the heartbeat — a site is only declared down after 45 minutes without contact,
 * so a 20-minute heartbeat still gives two chances inside that window.
 */
function tfm_heartbeat_interval() {
    $seconds = (int) apply_filters('tfm_heartbeat_interval', 20 * MINUTE_IN_SECONDS);
    // Keep it sane: never faster than 5 minutes, never slower than the monitor's
    // 45-minute down threshold.
    return max(5 * MINUTE_IN_SECONDS, min(45 * MINUTE_IN_SECONDS, $seconds));
}

add_filter('cron_schedules', function ($schedules) {
    $seconds = tfm_heartbeat_interval();
    $schedules['tfm_heartbeat'] = array(
        'interval' => $seconds,
        'display'  => sprintf('Every %d Minutes (TFM heartbeat)', max(1, (int) round($seconds / 60))),
    );
    // Retained so sites still holding an event on the old schedule have a valid
    // definition until the migration below moves them across.
    if (!isset($schedules['tfm_ten_minutes'])) {
        $schedules['tfm_ten_minutes'] = array(
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display'  => 'Every 10 Minutes (TFM, legacy)',
        );
    }
    return $schedules;
});

// Ensure the heartbeat is on the current schedule, migrating sites still on the
// earlier 15-minute and 10-minute ones.
add_action('init', function () {
    if (wp_get_schedule('tfm_heartbeat_event') !== 'tfm_heartbeat') {
        wp_clear_scheduled_hook('tfm_heartbeat_event');
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'tfm_heartbeat', 'tfm_heartbeat_event');
    }
});
add_action('tfm_heartbeat_event', 'tfm_send_heartbeat');

/**
 * The heartbeat endpoint (relay base with /api/heartbeat).
 */
function tfm_heartbeat_endpoint() {
    if (defined('TFM_HEARTBEAT_URL') && TFM_HEARTBEAT_URL) {
        return TFM_HEARTBEAT_URL;
    }
    $alert_url = function_exists('tfm_alert_relay_url') ? tfm_alert_relay_url() : '';
    if (empty($alert_url)) {
        return '';
    }
    $url = preg_replace('#/api/alert/?$#', '/api/heartbeat', $alert_url);
    return apply_filters('tfm_heartbeat_url', $url);
}

/**
 * Count non-empty, non-comment lines in a newline-separated pattern list.
 */
function tfm_heartbeat_count_patterns($raw) {
    if (empty($raw) || !is_string($raw)) {
        return 0;
    }
    $n = 0;
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ('' !== $line && 0 !== strpos($line, '#')) {
            $n++;
        }
    }
    return $n;
}

/**
 * Consent state for the heartbeat.
 *
 * Two consent systems can be present: TFM Tracking Consent (current) and the
 * older TFM Cookie Consent (deprecated). Tracking Consent wins when enabled;
 * otherwise the older module is reported so sites mid-migration still show
 * accurately. `system` records which one the numbers came from.
 *
 * Field names are deliberately shared between the two so the relay and the
 * fleet dashboard need no per-system branching.
 *
 * Booleans and counts only — never the banner copy or the blocked-script
 * patterns themselves, following the same "report state, never content" rule as
 * custom_scripts.
 *
 * `enforcing` is the field worth alerting on: a banner that is shown but backed
 * by nothing is worse than no banner, because it represents a choice to the
 * visitor that the site does not honour.
 */
function tfm_heartbeat_cookie_consent_state() {
    $tc = get_option('tfm_tc_settings', array());
    $cc = get_option('tfm_cookie_consent_settings', array());
    $tc = is_array($tc) ? $tc : array();
    $cc = is_array($cc) ? $cc : array();

    if (!empty($tc['enabled'])) {
        $banner        = true;
        $consent_mode  = !empty($tc['consent_mode']);
        $block_scripts = !empty($tc['blocking_enabled']);
        $block_iframes = !empty($tc['block_iframes']);
        $respect_gpc   = !empty($tc['respect_gpc']);
        $patterns      = tfm_heartbeat_count_patterns($tc['custom_patterns'] ?? '');
        $system        = 'tracking';
        // Receipts are unique to Tracking Consent; useful for spotting sites
        // with a banner but no proof-of-consent record.
        $receipts      = !empty($tc['logging_enabled']);
    } else {
        $banner        = !empty($cc['enabled']);
        $consent_mode  = !empty($cc['consent_mode']);
        $block_scripts = !empty($cc['prior_blocking']);
        $block_iframes = !empty($cc['block_iframes']);
        $respect_gpc   = !empty($cc['respect_gpc']);
        $patterns      = tfm_heartbeat_count_patterns($cc['blocked_script_patterns'] ?? '');
        $system        = $banner ? 'cookie' : 'none';
        $receipts      = false;
    }

    return array(
        'system'         => $system,
        'banner'         => $banner,
        'consent_mode'   => $consent_mode,
        'prior_blocking' => $block_scripts,
        'block_iframes'  => $block_iframes,
        'respect_gpc'    => $respect_gpc,
        'receipts'       => $receipts,
        'patterns'       => $patterns,
        // True only when the banner is shown AND something actually acts on the
        // visitor's choice. False with banner true = needs attention.
        'enforcing'      => $banner && ($consent_mode || $block_scripts || $block_iframes),
    );
}

/**
 * Send a heartbeat to the relay.
 */
function tfm_send_heartbeat() {
    $endpoint = tfm_heartbeat_endpoint();
    if (empty($endpoint)) {
        return;
    }

    // Deprecated custom-scripts usage — report only whether code is present and
    // its size, NEVER the code itself (it can contain secrets and must not leave
    // the site). Powers the fleet migration checklist.
    $settings = function_exists('tfm_load_settings') ? tfm_load_settings() : get_option('tfm_plugin_settings', array());
    $head_len = strlen(trim((string) ($settings['custom_head_scripts'] ?? '')));
    $foot_len = strlen(trim((string) ($settings['custom_footer_scripts'] ?? '')));

    // Update health — surfaces why a site might be stuck behind: auto-update
    // switched off, or a leftover GitHub token (which can 401 against the public
    // repo and silently block update checks). Mirrors the updater/auto-update logic.
    $auto_update_on   = !(defined('TFM_DISABLE_AUTO_UPDATE') && TFM_DISABLE_AUTO_UPDATE);
    $auto_update_on   = (bool) apply_filters('tfm_enable_auto_update', $auto_update_on);
    $update_token_set = (defined('TFM_GITHUB_TOKEN') && TFM_GITHUB_TOKEN) ? true : !empty($settings['github_token']);

    $payload = array(
        'site_url'       => home_url(),
        // Decode HTML entities so names with apostrophes/ampersands aren't stored
        // pre-encoded (get_bloginfo returns e.g. "Joe&#039;s", which then double-
        // encodes in the dashboard).
        'site_name'      => html_entity_decode(get_bloginfo('name'), ENT_QUOTES),
        'plugin_version' => defined('TFM_PLUGIN_VERSION') ? TFM_PLUGIN_VERSION : '',
        'php_version'    => PHP_VERSION,
        'wp_version'     => get_bloginfo('version'),
        'custom_scripts' => array(
            'head'        => $head_len > 0,
            'footer'      => $foot_len > 0,
            'total_bytes' => $head_len + $foot_len,
        ),
        // Whether Secure Custom Fields / ACF is active (the only thing that used
        // it was Press Releases, now optional) — powers the "safe to remove SCF"
        // fleet view.
        'scf_active'     => function_exists('acf_add_local_field_group'),
        // Search-engine indexing state (WP Settings → Reading → "Discourage
        // search engines"). blog_public = 1 means indexing is allowed; 0 means
        // discouraged (noindex). Flags live sites accidentally left non-indexable.
        'search_indexing' => (bool) get_option('blog_public', 1),
        // Update health (see above) — powers the dashboard's "why is this site
        // behind?" flags.
        'auto_update'      => $auto_update_on,
        'update_token_set' => $update_token_set,
        // Cookie consent state, per-flag. `banner` is the headline: which sites
        // actually show a consent banner. The rest report whether the banner is
        // backed by real enforcement — a banner with everything else false asks
        // for a choice it does not act on, which is the state every site was in
        // before 3.26.0. Powers the fleet cookie-consent view and makes the
        // post-deploy spot-check list self-generating.
        'cookie_consent' => tfm_heartbeat_cookie_consent_state(),
        'timestamp'      => current_time('mysql'),
    );

    wp_remote_post($endpoint, array(
        'timeout'   => 5,
        'blocking'  => false,
        'headers'   => array('Content-Type' => 'application/json'),
        'body'      => wp_json_encode($payload),
        'sslverify' => true,
    ));
}
