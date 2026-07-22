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

// Add a 15-minute cron interval.
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['tfm_fifteen_minutes'])) {
        $schedules['tfm_fifteen_minutes'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => 'Every 15 Minutes (TFM)',
        );
    }
    return $schedules;
});

// Ensure the heartbeat is scheduled.
add_action('init', function () {
    if (!wp_next_scheduled('tfm_heartbeat_event')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'tfm_fifteen_minutes', 'tfm_heartbeat_event');
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
 * Send a heartbeat to the relay.
 */
function tfm_send_heartbeat() {
    $endpoint = tfm_heartbeat_endpoint();
    if (empty($endpoint)) {
        return;
    }

    $payload = array(
        'site_url'       => home_url(),
        'site_name'      => get_bloginfo('name'),
        'plugin_version' => defined('TFM_PLUGIN_VERSION') ? TFM_PLUGIN_VERSION : '',
        'php_version'    => PHP_VERSION,
        'wp_version'     => get_bloginfo('version'),
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
