<?php
/**
 * TFM Cookie Consent
 *
 * GDPR-compliant cookie consent banner. Absorbed from the standalone
 * "TFM Cookie Consent" plugin — its classes and assets live under
 * includes/cookie-consent/, and the settings option (`tfm_cookie_consent_settings`)
 * and class names are preserved so existing configuration keeps working.
 */

if (!defined('ABSPATH')) {
    exit;
}

// If the standalone "TFM Cookie Consent" is still active, stay dormant this
// request and deactivate it so TFM takes over cleanly on the next load (avoids
// a fatal class redeclaration during the overlap).
if (tfm_handover_absorbed_plugin(
    array('tfm-cookie-consent.php', 'tfm-cookie-consent', 'TFM-Cookie-Consent'),
    array('TFM Cookie Consent'),
    'cookie_consent'
)) {
    return;
}

// Constants the cookie-consent classes expect. Pointed at the sub-directory so
// their internal asset paths (assets/css, assets/js) resolve correctly.
if (!defined('TFM_COOKIE_CONSENT_VERSION')) {
    define('TFM_COOKIE_CONSENT_VERSION', TFM_PLUGIN_VERSION);
    define('TFM_COOKIE_CONSENT_PLUGIN_URL', TFM_PLUGIN_URL . 'includes/cookie-consent/');
    define('TFM_COOKIE_CONSENT_PLUGIN_PATH', TFM_PLUGIN_DIR . 'includes/cookie-consent/');
    define('TFM_COOKIE_CONSENT_PLUGIN_BASENAME', plugin_basename(TFM_PLUGIN_DIR . 'topfiremedia.php'));
}

/**
 * Load the cookie-consent classes and initialize its components.
 */
function tfm_cookie_consent_init() {
    $base = TFM_COOKIE_CONSENT_PLUGIN_PATH . 'includes/';
    require_once $base . 'class-tfm-cookie-consent-admin.php';
    require_once $base . 'class-tfm-cookie-consent-frontend.php';
    require_once $base . 'class-tfm-cookie-consent-ajax.php';
    require_once $base . 'class-tfm-cookie-consent-settings.php';
    require_once $base . 'class-tfm-cookie-consent-cookies.php';

    new TFM_Cookie_Consent_Admin();
    new TFM_Cookie_Consent_Frontend();
    new TFM_Cookie_Consent_Ajax();
    new TFM_Cookie_Consent_Cookies();
}
add_action('init', 'tfm_cookie_consent_init');

/**
 * Load translations from the sub-directory.
 */
function tfm_cookie_consent_load_textdomain() {
    load_textdomain(
        'tfm-cookie-consent',
        TFM_COOKIE_CONSENT_PLUGIN_PATH . 'languages/tfm-cookie-consent-' . get_locale() . '.mo'
    );
}
add_action('plugins_loaded', 'tfm_cookie_consent_load_textdomain');

/**
 * Seed default cookie-consent settings on installs that don't have them yet.
 * add_option() is a no-op when the option already exists, so existing sites
 * keep their configuration untouched.
 */
function tfm_cookie_consent_default_options() {
    if (get_option('tfm_cookie_consent_settings') !== false) {
        return;
    }

    add_option('tfm_cookie_consent_settings', array(
        'enabled'                 => true,
        'popup_title'             => __('Cookie Consent', 'tfm-cookie-consent'),
        'popup_message'           => __('This website uses cookies to ensure you get the best experience on our website.', 'tfm-cookie-consent'),
        'accept_button_text'      => __('Accept All', 'tfm-cookie-consent'),
        'deny_button_text'        => __('Deny All', 'tfm-cookie-consent'),
        'customize_button_text'   => __('Customize', 'tfm-cookie-consent'),
        'cookie_categories'       => array(
            'necessary'  => array('name' => __('Necessary', 'tfm-cookie-consent'),  'description' => __('These cookies are essential for the website to function properly.', 'tfm-cookie-consent'), 'required' => true,  'enabled' => true),
            'analytics'  => array('name' => __('Analytics', 'tfm-cookie-consent'),  'description' => __('These cookies help us understand how visitors interact with our website.', 'tfm-cookie-consent'), 'required' => false, 'enabled' => false),
            'marketing'  => array('name' => __('Marketing', 'tfm-cookie-consent'),  'description' => __('These cookies are used to track visitors across websites for marketing purposes.', 'tfm-cookie-consent'), 'required' => false, 'enabled' => false),
            'functional' => array('name' => __('Functional', 'tfm-cookie-consent'), 'description' => __('These cookies enable enhanced functionality and personalization.', 'tfm-cookie-consent'), 'required' => false, 'enabled' => false),
        ),
        'popup_position'          => 'bottom',
        'popup_theme'             => 'light',
        'expiry_days'             => 365,
        'background_color'        => '#ffffff',
        'text_color'              => '#333333',
        'secondary_text_color'    => '#666666',
        'border_color'            => '#eeeeee',
        'accept_button_color'     => '#27ae60',
        'accept_button_text_color'=> '#ffffff',
        'deny_button_color'       => '#e74c3c',
        'deny_button_text_color'  => '#ffffff',
        'customize_button_color'  => '#3498db',
        'customize_button_text_color' => '#ffffff',
        'save_button_color'       => '#27ae60',
        'save_button_text_color'  => '#ffffff',
        'back_button_color'       => '#95a5a6',
        'back_button_text_color'  => '#ffffff',
        'toggle_active_color'     => '#27ae60',
        'toggle_inactive_color'   => '#cccccc',
        'category_background_color'=> '#fafafa',
        'overlay_color'           => '#000000',
        'overlay_opacity'         => 50,
        'google_analytics'        => false,
        'google_tag_manager'      => false,
        'facebook_pixel'          => false,
        'custom_scripts'          => '',
    ));
}
add_action('init', 'tfm_cookie_consent_default_options', 5);
