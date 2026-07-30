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

// Add a 10-minute cron interval.
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['tfm_ten_minutes'])) {
        $schedules['tfm_ten_minutes'] = array(
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display'  => 'Every 10 Minutes (TFM)',
        );
    }
    return $schedules;
});

// Ensure the heartbeat is scheduled on the 10-minute interval, migrating sites
// still on the previous 15-minute schedule.
add_action('init', function () {
    $schedule = wp_get_schedule('tfm_heartbeat_event');
    if ($schedule !== 'tfm_ten_minutes') {
        if ($schedule !== false) {
            wp_clear_scheduled_hook('tfm_heartbeat_event');
        }
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'tfm_ten_minutes', 'tfm_heartbeat_event');
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
 * Cookie consent state for the heartbeat.
 *
 * Booleans only — no banner copy, no blocked-script patterns. The patterns are
 * only vendor hostnames, but there is no fleet-view reason to ship them and the
 * heartbeat keeps to the "report state, never content" rule used for custom
 * scripts.
 *
 * `enforcing` is the field worth alerting on: a banner that is shown but backed
 * by nothing is worse than no banner, because it represents a choice to the
 * visitor that the site does not honour.
 */
function tfm_heartbeat_cookie_consent_state() {
    $cc = get_option('tfm_cookie_consent_settings', array());
    if (!is_array($cc)) {
        $cc = array();
    }

    $banner        = !empty($cc['enabled']);
    $consent_mode  = !empty($cc['consent_mode']);
    $block_scripts = !empty($cc['prior_blocking']);
    $block_iframes = !empty($cc['block_iframes']);

    $patterns = 0;
    if (!empty($cc['blocked_script_patterns'])) {
        foreach (preg_split('/\r\n|\r|\n/', $cc['blocked_script_patterns']) as $line) {
            $line = trim($line);
            if ('' !== $line && 0 !== strpos($line, '#')) {
                $patterns++;
            }
        }
    }

    return array(
        'banner'         => $banner,
        'consent_mode'   => $consent_mode,
        'prior_blocking' => $block_scripts,
        'block_iframes'  => $block_iframes,
        'respect_gpc'    => !empty($cc['respect_gpc']),
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
