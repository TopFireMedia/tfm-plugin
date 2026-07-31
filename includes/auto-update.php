<?php
/**
 * TFM plugin auto-updates + staged rollout.
 *
 * Enables WordPress background auto-updates for TFM Custom Functions itself, so a
 * published release rolls out across the fleet within hours (on WP's update cron)
 * with no per-site action. The Plugin Update Checker supplies the "an update is
 * available" data from the TFM repo; this tells WordPress it's allowed to apply
 * it — subject to an optional fleet ROLLOUT POLICY served by the relay:
 *
 *   - hold_at: the highest version the fleet may auto-update to. Publish a new
 *     release, keep the fleet pinned at the previous version, update a canary or
 *     two by hand, verify on the dashboard, then lift the hold to release to all.
 *   - canary: hosts that ignore the hold and always take the latest.
 *
 * The policy is fetched from the relay and cached (~30 min). It FAILS OPEN — if
 * the relay is unreachable, normal auto-update proceeds — so a relay outage can
 * never freeze the fleet's updates.
 *
 * On by default. Disable on a specific site with:
 *   define('TFM_DISABLE_AUTO_UPDATE', true);      // in wp-config.php, or
 *   add_filter('tfm_enable_auto_update', '__return_false');
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('auto_update_plugin', function ($update, $item) {
    $enabled = !(defined('TFM_DISABLE_AUTO_UPDATE') && TFM_DISABLE_AUTO_UPDATE);
    $enabled = apply_filters('tfm_enable_auto_update', $enabled);
    if (!$enabled) {
        return $update;
    }

    // Our own plugin file, e.g. "tfm-plugin-main/topfiremedia.php".
    $our_basename = plugin_basename(TFM_PLUGIN_DIR . 'topfiremedia.php');

    $candidate = '';
    if (is_object($item)) {
        if (!empty($item->plugin)) {
            $candidate = $item->plugin;
        } elseif (!empty($item->slug)) {
            $candidate = $item->slug;
        }
    }

    $is_ours = ($candidate === $our_basename
        || $candidate === dirname($our_basename)
        || basename((string) $candidate) === 'topfiremedia.php');

    if (!$is_ours) {
        return $update;
    }

    // Staged rollout: canary sites always take the latest; everyone else is held
    // at the pinned version until it's lifted.
    $policy = tfm_rollout_policy();
    $host   = function_exists('home_url') ? strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)) : '';
    $canary = array_map('strtolower', (array) ($policy['canary'] ?? array()));

    if ($host && in_array($host, $canary, true)) {
        return true; // canary — always update
    }

    if (!empty($policy['hold_at'])) {
        $new_version = is_object($item) && !empty($item->new_version) ? $item->new_version : '';
        if ($new_version && version_compare($new_version, (string) $policy['hold_at'], '>')) {
            return false; // held back: don't auto-update past the pinned version
        }
    }

    return true;
}, 10, 2);

/**
 * Fetch the fleet rollout policy from the relay (cached ~30 min). Fails open:
 * on any error it returns an empty policy so normal auto-update proceeds.
 *
 * @return array { hold_at: string|null, canary: string[] }
 */
function tfm_rollout_policy() {
    $cached = get_transient('tfm_rollout_policy');
    if (is_array($cached)) {
        return $cached;
    }

    $empty = array('hold_at' => null, 'canary' => array());
    $url   = tfm_rollout_url();
    if (empty($url)) {
        return $empty;
    }

    $resp = wp_remote_get($url, array('timeout' => 4));
    if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) !== 200) {
        set_transient('tfm_rollout_policy', $empty, 10 * MINUTE_IN_SECONDS); // short cache on failure
        return $empty;
    }

    $data   = json_decode(wp_remote_retrieve_body($resp), true);
    $policy = array(
        'hold_at' => (is_array($data) && !empty($data['hold_at'])) ? (string) $data['hold_at'] : null,
        'canary'  => (is_array($data) && !empty($data['canary']) && is_array($data['canary'])) ? $data['canary'] : array(),
    );
    set_transient('tfm_rollout_policy', $policy, 30 * MINUTE_IN_SECONDS);
    return $policy;
}

/**
 * The rollout-policy endpoint (relay base + /api/rollout). Derived from the alert
 * relay URL; override with TFM_ROLLOUT_URL.
 *
 * @return string
 */
function tfm_rollout_url() {
    if (defined('TFM_ROLLOUT_URL') && TFM_ROLLOUT_URL) {
        return TFM_ROLLOUT_URL;
    }
    $alert = function_exists('tfm_alert_relay_url') ? tfm_alert_relay_url() : '';
    if (empty($alert)) {
        return '';
    }
    return preg_replace('#/api/alert/?$#', '/api/rollout', $alert);
}
