<?php
/**
 * TFM ClickUp Alerts
 *
 * Sends critical activity-log events to a central ClickUp "Site Alerts" list via
 * a TFM relay endpoint. The relay holds the ClickUp API token server-side and
 * forwards each alert as a ClickUp task, so no credential ever lives on a site —
 * the site only needs the relay URL, which is not a secret.
 *
 * Config (all optional; relay URL has a baked-in default once the relay is live):
 *   - define('TFM_ALERT_RELAY_URL', 'https://…');   // override the relay endpoint
 *   - add_filter('tfm_alert_actions', …);            // change which events alert
 *   - add_filter('tfm_alert_throttle_seconds', …);   // change the anti-flood window
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The relay endpoint. Not a secret (it only receives alerts; the ClickUp token
 * lives at the relay). Override via the TFM_ALERT_RELAY_URL constant or filter.
 */
function tfm_alert_relay_url() {
    $url = defined('TFM_ALERT_RELAY_URL') && TFM_ALERT_RELAY_URL
        ? TFM_ALERT_RELAY_URL
        : ''; // set the deployed relay URL here (or via the constant) to enable
    return apply_filters('tfm_alert_relay_url', $url);
}

/**
 * Which logged actions trigger a ClickUp alert. Filterable.
 */
function tfm_alert_actions() {
    return apply_filters('tfm_alert_actions', array(
        'plugin_activated',
        'plugin_deactivated',
        'plugin_deleted',
        'theme_switched',
        'user_role_changed',
        'user_deleted',
        'user_login_failed',
        'post_deleted',
        'core_updated',
    ));
}

/**
 * Dispatch a ClickUp alert for a critical logged event. Hooked to the logger's
 * tfm_activity_logged action so it reuses all the event capture + actor identity.
 *
 * @param array $entry The full log entry.
 */
function tfm_clickup_alert_on_log($entry) {
    if (!is_array($entry) || empty($entry['action'])) {
        return;
    }
    if (!in_array($entry['action'], tfm_alert_actions(), true)) {
        return;
    }

    $relay = tfm_alert_relay_url();
    if (empty($relay)) {
        return; // relay not configured — alerting disabled
    }

    // Anti-flood: at most one alert per action+actor per window (default 5 min),
    // so a brute-force login burst doesn't create 100 tasks.
    $throttle_key = 'tfm_alert_' . md5($entry['action'] . '|' . ($entry['user_login'] ?? ''));
    if (get_transient($throttle_key)) {
        return;
    }
    set_transient($throttle_key, 1, apply_filters('tfm_alert_throttle_seconds', 5 * MINUTE_IN_SECONDS));

    $payload = array(
        'site_name'  => get_bloginfo('name'),
        'site_url'   => home_url(),
        'action'     => $entry['action'],
        'severity'   => $entry['severity'] ?? 'info',
        'user'       => !empty($entry['user_display_name']) ? $entry['user_display_name'] : ($entry['user_login'] ?? 'unknown'),
        'user_login' => $entry['user_login'] ?? '',
        'context'    => $entry['context'] ?? '',
        'ip'         => $entry['ip_address'] ?? '',
        'timestamp'  => $entry['timestamp'] ?? current_time('mysql'),
        'data'       => is_array($entry['data'] ?? null) ? $entry['data'] : array(),
    );

    // Non-blocking so it never slows the request that triggered it.
    wp_remote_post($relay, array(
        'timeout'   => 2,
        'blocking'  => false,
        'headers'   => array('Content-Type' => 'application/json'),
        'body'      => wp_json_encode($payload),
        'sslverify' => true,
    ));
}
add_action('tfm_activity_logged', 'tfm_clickup_alert_on_log');
