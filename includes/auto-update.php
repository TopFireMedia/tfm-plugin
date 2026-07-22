<?php
/**
 * TFM plugin auto-updates.
 *
 * Enables WordPress background auto-updates for TFM Custom Functions itself, so a
 * published release rolls out across the fleet within hours (on WP's twice-daily
 * update cron) with no per-site action. The Plugin Update Checker still supplies
 * the "an update is available" data from the TFM repo; this just tells WordPress
 * it's allowed to apply it automatically.
 *
 * On by default. To disable on a specific site:
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

    if ($candidate === $our_basename
        || $candidate === dirname($our_basename)
        || basename((string) $candidate) === 'topfiremedia.php') {
        return true;
    }

    return $update;
}, 10, 2);
