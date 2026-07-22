<?php
/**
 * TFM Upgrade Routines
 * One-time, version-keyed migrations (enable logging, strip redundant phone
 * scripts). Moved verbatim from topfiremedia.php during modularization.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Run one-time upgrade routines, keyed by a stored DB version so each runs once.
 * Fires on admin_init (cheap, only compares two version strings on most loads).
 */
function tfm_maybe_run_upgrades() {
    $installed = get_option('tfm_plugin_db_version', '0');
    if (version_compare($installed, TFM_PLUGIN_VERSION, '>=')) {
        return; // already up to date
    }

    // 3.14.0 — turn the activity log on across existing installs so the
    // accountability audit trail is running without per-site toggling. Runs
    // once; admins can disable afterward and it won't be re-enabled.
    if (version_compare($installed, '3.14.0', '<')) {
        $settings = get_option('tfm_plugin_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings['enable_logging'] = true;
        if (empty($settings['log_level']) || $settings['log_level'] === 'error') {
            $settings['log_level'] = 'all';
        }
        update_option('tfm_plugin_settings', $settings);
    }

    // 3.15.0 — the phone formatter now handles ALL tel fields, so the manual
    // "format all input[type=tel]" scripts some sites pasted into Custom Head/
    // Footer Scripts are redundant. Remove just that script block (other custom
    // scripts are preserved). Opt out with:
    //   define('TFM_KEEP_LEGACY_PHONE_SCRIPTS', true);
    if (version_compare($installed, '3.15.0', '<')
        && !(defined('TFM_KEEP_LEGACY_PHONE_SCRIPTS') && TFM_KEEP_LEGACY_PHONE_SCRIPTS)) {
        tfm_remove_legacy_phone_scripts();
    }

    update_option('tfm_plugin_db_version', TFM_PLUGIN_VERSION);
}

/**
 * One-time cleanup: strip the redundant "format all tel inputs" phone script
 * that some sites pasted into Custom Head/Footer Scripts, now that the plugin's
 * phone formatter handles every tel field. Removes ONLY a <script> block whose
 * body both targets tel inputs AND formats phone numbers; all other custom
 * script content is preserved. The removal is recorded in the activity log.
 */
function tfm_remove_legacy_phone_scripts() {
    $settings = get_option('tfm_plugin_settings', []);
    if (!is_array($settings)) {
        return;
    }

    $removed = false;
    foreach (['custom_footer_scripts', 'custom_head_scripts'] as $key) {
        if (empty($settings[$key]) || !is_string($settings[$key])) {
            continue;
        }
        $cleaned = tfm_strip_legacy_phone_script($settings[$key]);
        if ($cleaned !== $settings[$key]) {
            $settings[$key] = $cleaned;
            $removed = true;
        }
    }

    if ($removed) {
        update_option('tfm_plugin_settings', $settings);
        if (function_exists('tfm_log_action')) {
            tfm_log_action('option_updated', [
                'option_name' => 'tfm_plugin_settings (custom scripts)',
                'old_value'   => 'legacy phone-formatting script present',
                'new_value'   => 'removed automatically (now redundant with the built-in phone formatter)',
            ]);
        }
    }
}

/**
 * Remove <script> blocks that BOTH target tel inputs AND format phone numbers.
 * Conservative: it requires both signals, so unrelated custom scripts are never
 * touched. Handles the HTML-entity/backslash corruption the old save path
 * introduced (e.g. &gt;, input[type=\"tel\"]).
 */
function tfm_strip_legacy_phone_script($content) {
    if (stripos($content, '<script') === false) {
        return $content;
    }

    $cleaned = preg_replace_callback(
        '#<script\b[^>]*>(.*?)</script\s*>#is',
        function ($m) {
            $body = $m[1];

            $targets_tel = (stripos($body, 'type="tel"') !== false && stripos($body, 'input') !== false)
                        || (stripos($body, "type='tel'") !== false && stripos($body, 'input') !== false)
                        || (stripos($body, 'type=\\"tel\\"') !== false);

            $formats_phone = (stripos($body, "removeattr('pattern')") !== false)
                          || (stripos($body, 'removeattr("pattern")') !== false)
                          || (stripos($body, '.substring(0, 3)') !== false)
                          || (stripos($body, '.substring(0,3)') !== false)
                          || (stripos($body, "'+1'") !== false && stripos($body, '.substring') !== false)
                          || (stripos($body, 'tfmphonenumber') !== false && stripos($body, '.val(') !== false);

            return ($targets_tel && $formats_phone) ? '' : $m[0];
        },
        $content
    );

    if ($cleaned === null) {
        return $content; // regex error — leave content untouched
    }

    if ($cleaned !== $content) {
        $cleaned = preg_replace("/(\r?\n){3,}/", "\n\n", $cleaned);
        if (trim($cleaned) === '') {
            $cleaned = '';
        }
    }

    return $cleaned;
}
// Run on 'init' (fires on front-end, admin, and cron) so logging is enabled on
// the very first request after the update, not only when an admin visits wp-admin.
add_action('init', 'tfm_maybe_run_upgrades');

/**
 * Normalize a plugin file/folder identifier for loose matching: lowercase, drop
 * a trailing ".php", and treat spaces/underscores as hyphens. So
 * "Press Release Manager", "press-release-manager", and
 * "press-release-manager.php" all normalize to "press-release-manager".
 *
 * @param string $id
 * @return string
 */
function tfm_norm_plugin_id($id) {
    $id = strtolower((string) $id);
    $id = preg_replace('/\.php$/', '', $id);
    $id = str_replace(array(' ', '_'), '-', $id);
    return trim($id);
}

/**
 * Safe handover when a standalone plugin has been absorbed into TFM.
 *
 * If the absorbed standalone is still active, TFM must NOT define its absorbed
 * copy this request — otherwise the two would redeclare the same classes/CPT and
 * fatal. This returns true (telling the caller to stay dormant) and deactivates
 * the standalone so TFM cleanly takes over on the next load. Runs fleet-wide,
 * including on sites without login access.
 *
 * Matching is deliberately loose because the same in-house plugin is packaged
 * inconsistently across the fleet (different folder names, capitalization, even
 * different main-file names):
 *   1. Cheap: any active plugin whose main-file OR folder name matches one of
 *      $candidates after normalization (case / spaces / underscores / .php).
 *   2. Fallback: any active plugin whose declared "Plugin Name" header matches
 *      one of $display_names. The header scan is cached against the current
 *      active-plugins signature, so it runs only when the plugin set changes —
 *      not on every request.
 *
 * @param string|array $candidates    File/folder identifiers, e.g. 'press-release-manager.php'.
 * @param array        $display_names Declared plugin names to match, e.g. ['Press Release Manager'].
 * @param string       $slug          Stable key for caching the header scan.
 * @return bool True if a matching standalone was found and deactivated.
 */
function tfm_handover_absorbed_plugin($candidates, $display_names = array(), $slug = '') {
    $active = (array) get_option('active_plugins', array());

    $wanted = array();
    foreach ((array) $candidates as $c) {
        $n = tfm_norm_plugin_id($c);
        if ($n !== '' && $n !== '.') {
            $wanted[$n] = true;
        }
    }

    // 1) Cheap normalized match on the active-plugins entries (works even if the
    //    plugin's files are missing — clears stale/ghost entries too).
    $found = null;
    foreach ($active as $plugin) {
        $base = tfm_norm_plugin_id(basename($plugin));
        $dir  = tfm_norm_plugin_id(dirname($plugin));
        if (isset($wanted[$base]) || ($dir !== '.' && $dir !== '' && isset($wanted[$dir]))) {
            $found = $plugin;
            break;
        }
    }

    // 2) Fallback: match by the declared "Plugin Name" header, for odd packagings
    //    the name check above can miss. Cached by active-plugins signature.
    if ($found === null && !empty($display_names) && $slug) {
        $sig       = md5(implode('|', $active));
        $cache_key = 'tfm_handover_scan_' . $slug;
        $cached    = get_option($cache_key);
        if (!is_array($cached) || ($cached['sig'] ?? '') !== $sig) {
            if (!function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $want_names = array_map(function ($n) { return strtolower(trim($n)); }, (array) $display_names);
            foreach ($active as $plugin) {
                $path = WP_PLUGIN_DIR . '/' . $plugin;
                if (!is_readable($path)) {
                    continue;
                }
                $data = get_plugin_data($path, false, false);
                if (!empty($data['Name']) && in_array(strtolower(trim($data['Name'])), $want_names, true)) {
                    $found = $plugin;
                    break;
                }
            }
            if ($found === null) {
                // Record that this exact plugin set has no name match, so we skip
                // the header scan until the active-plugins list changes.
                update_option($cache_key, array('sig' => $sig), false);
            } else {
                delete_option($cache_key);
            }
        }
    }

    if ($found === null) {
        return false; // standalone not active — TFM provides the feature
    }

    if (!function_exists('deactivate_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    deactivate_plugins($found, true); // silent; takes effect next request

    if (function_exists('tfm_log_action')) {
        tfm_log_action('plugin_deactivated', array(
            'plugin' => $found . ' (absorbed into TFM Custom Functions)',
        ));
    }
    return true;
}
