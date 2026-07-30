<?php
/**
 * TFM Tracking Consent
 *
 * Absorbed from the standalone "TFM Tracking Consent" plugin — its classes and
 * assets live under includes/tracking-consent/, and the settings option
 * (`tfm_tc_settings`), class names and text domain are preserved so existing
 * configuration keeps working.
 *
 * This SUPERSEDES includes/cookie-consent.php. That older module only recorded
 * a preference; this one enforces it — server-side tag rewriting, a
 * document.createElement gate that catches trackers injected at runtime (which
 * a server-side rewrite alone cannot), a MutationObserver fallback, Google
 * Consent Mode v2 with wait_for_update, and consent receipts. It is also
 * page-cache safe by design: blocking is identical for every visitor and
 * unblocking happens in the browser.
 *
 * The old cookie-consent module is left in place for sites that still hold its
 * option, but should not be enabled alongside this one.
 */

if (!defined('ABSPATH')) {
    exit;
}

// If the standalone "TFM Tracking Consent" is still active, hand over: record
// that this site was actually using it (provenance), then stay dormant this
// request while the handover deactivates the standalone, so TFM takes over
// cleanly on the next load without a class redeclaration.
if (tfm_handover_absorbed_plugin(
    array('tfm-tracking-consent.php', 'tfm-tracking-consent', 'TFM-Tracking-Consent'),
    array('TFM Tracking Consent'),
    'tracking_consent'
)) {
    // A site running the standalone was deliberately using consent management,
    // so make sure the absorbed copy stays on for it.
    update_option('tfm_tc_activated', 1);
    $tfm_tc = get_option('tfm_tc_settings');
    if (is_array($tfm_tc) && empty($tfm_tc['enabled'])) {
        $tfm_tc['enabled'] = 1;
        update_option('tfm_tc_settings', $tfm_tc);
    }
    return;
}

// Constants the tracking-consent classes expect, pointed at the sub-directory
// so their asset URLs resolve. TFM_TC_FILE is only used to build the
// plugin_action_links_ hook, so the TFM plugin file is the correct target —
// the settings link then appears on the TFM plugin row.
if (!defined('TFM_TC_VERSION')) {
    define('TFM_TC_VERSION', TFM_PLUGIN_VERSION);
    define('TFM_TC_FILE', TFM_PLUGIN_DIR . 'topfiremedia.php');
    define('TFM_TC_DIR', TFM_PLUGIN_DIR . 'includes/tracking-consent/');
    define('TFM_TC_URL', TFM_PLUGIN_URL . 'includes/tracking-consent/');
    define('TFM_TC_OPTION', 'tfm_tc_settings');
}

/**
 * Load the tracking-consent classes and boot its components.
 */
function tfm_tracking_consent_init() {
    $base = TFM_TC_DIR . 'includes/';

    require_once $base . 'class-tfm-tc-settings.php';
    require_once $base . 'class-tfm-tc-services.php';
    require_once $base . 'class-tfm-tc-blocker.php';
    require_once $base . 'class-tfm-tc-frontend.php';
    require_once $base . 'class-tfm-tc-shortcodes.php';
    require_once $base . 'class-tfm-tc-consent-log.php';
    require_once $base . 'class-tfm-tc-plugin.php';

    if (is_admin()) {
        require_once $base . 'class-tfm-tc-admin.php';
    }

    TFM_TC_Plugin::instance();
}
add_action('plugins_loaded', 'tfm_tracking_consent_init');

/**
 * Main accessor, preserved from the standalone for any site-level code that
 * called it.
 *
 * @return TFM_TC_Plugin
 */
function tfm_tc() {
    return TFM_TC_Plugin::instance();
}

/**
 * Seed defaults on installs that don't have them yet.
 *
 * The standalone shipped with 'enabled' => 1 because activating a consent
 * plugin is itself the opt-in. Absorbed into TFM it arrives on every site at
 * once, so it is forced OFF unless this site was migrated from an active
 * standalone (provenance recorded in 'tfm_tc_activated'). Turning a consent
 * banner on unattended would change what every client site renders.
 */
function tfm_tracking_consent_default_options() {
    if (get_option('tfm_tc_settings') !== false) {
        return;
    }
    if (!class_exists('TFM_TC_Settings')) {
        return;
    }

    $defaults            = TFM_TC_Settings::defaults();
    $defaults['enabled'] = get_option('tfm_tc_activated', false) ? 1 : 0;

    add_option('tfm_tc_settings', $defaults);
}
add_action('init', 'tfm_tracking_consent_default_options', 5);

/**
 * Create the consent-receipt table when needed.
 *
 * The standalone did this from its activation hook, which never fires for an
 * absorbed module. dbDelta is idempotent, so this runs once per schema version
 * and again only if the version is bumped.
 */
function tfm_tracking_consent_maybe_create_table() {
    if (!class_exists('TFM_TC_Consent_Log') || !class_exists('TFM_TC_Settings')) {
        return;
    }
    if (!TFM_TC_Settings::get('logging_enabled')) {
        return; // No receipts requested; don't create an unused table.
    }

    $schema = '1';
    if (get_option('tfm_tc_table_version') === $schema) {
        return;
    }

    TFM_TC_Consent_Log::create_table();
    update_option('tfm_tc_table_version', $schema);
}
add_action('init', 'tfm_tracking_consent_maybe_create_table', 6);

/**
 * Warn if both consent systems are enabled at once. Two banners and two
 * blockers on one page is a support call waiting to happen, and the older
 * module is deprecated.
 */
function tfm_tracking_consent_conflict_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $tc = get_option('tfm_tc_settings', array());
    $cc = get_option('tfm_cookie_consent_settings', array());
    if (empty($tc['enabled']) || empty($cc['enabled'])) {
        return;
    }
    echo '<div class="notice notice-warning"><p><strong>' .
        esc_html__('Two consent systems are enabled.', 'tfm-tracking-consent') .
        '</strong> ' .
        esc_html__('TFM Tracking Consent and the older TFM Cookie Consent are both on. Disable the older Cookie Consent under Settings → Cookie Consent — Tracking Consent supersedes it.', 'tfm-tracking-consent') .
        '</p></div>';
}
add_action('admin_notices', 'tfm_tracking_consent_conflict_notice');

/**
 * Load translations from the sub-directory.
 */
function tfm_tracking_consent_load_textdomain() {
    load_textdomain(
        'tfm-tracking-consent',
        TFM_TC_DIR . 'languages/tfm-tracking-consent-' . get_locale() . '.mo'
    );
}
add_action('plugins_loaded', 'tfm_tracking_consent_load_textdomain', 5);
