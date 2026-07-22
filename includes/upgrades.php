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
