<?php
/**
 * Settings functionality for TFM Cookie Consent
 *
 * @package TFM_Cookie_Consent
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class
 */
class TFM_Cookie_Consent_Settings {
    
    /**
     * Get plugin settings
     */
    public static function get_settings() {
        return get_option('tfm_cookie_consent_settings', array());
    }
    
    /**
     * Update plugin settings
     */
    public static function update_settings($settings) {
        return update_option('tfm_cookie_consent_settings', $settings);
    }
    
    /**
     * Get a specific setting
     */
    public static function get_setting($key, $default = null) {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Update a specific setting
     */
    public static function update_setting($key, $value) {
        $settings = self::get_settings();
        $settings[$key] = $value;
        return self::update_settings($settings);
    }
    
    /**
     * Check if plugin is enabled
     */
    public static function is_enabled() {
        return self::get_setting('enabled', true);
    }
    
    /**
     * Get cookie categories
     */
    public static function get_cookie_categories() {
        return self::get_setting('cookie_categories', array());
    }
    
    /**
     * Get integrations
     */
    public static function get_integrations() {
        return self::get_setting('integrations', array());
    }
    
    /**
     * Get consent log
     */
    public static function get_consent_log() {
        return get_option('tfm_cookie_consent_log', array());
    }
    
    /**
     * Clear consent log
     */
    public static function clear_consent_log() {
        return delete_option('tfm_cookie_consent_log');
    }
    
    /**
     * Get plugin statistics
     */
    public static function get_statistics() {
        $log = self::get_consent_log();
        $stats = array(
            'total_consents' => count($log),
            'accept_all' => 0,
            'deny_all' => 0,
            'customize' => 0,
            'categories' => array()
        );
        
        foreach ($log as $entry) {
            if ($entry['all_accepted']) {
                $stats['accept_all']++;
            } elseif ($entry['all_denied']) {
                $stats['deny_all']++;
            } else {
                $stats['customize']++;
            }
            
            if (isset($entry['categories'])) {
                foreach ($entry['categories'] as $category => $accepted) {
                    if (!isset($stats['categories'][$category])) {
                        $stats['categories'][$category] = array('accepted' => 0, 'denied' => 0);
                    }
                    if ($accepted) {
                        $stats['categories'][$category]['accepted']++;
                    } else {
                        $stats['categories'][$category]['denied']++;
                    }
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Export settings
     */
    public static function export_settings() {
        $settings = self::get_settings();
        $log = self::get_consent_log();
        
        return array(
            'settings' => $settings,
            'consent_log' => $log,
            'export_date' => current_time('mysql'),
            'version' => TFM_COOKIE_CONSENT_VERSION
        );
    }
    
    /**
     * Import settings
     */
    public static function import_settings($data) {
        if (!is_array($data) || !isset($data['settings'])) {
            return false;
        }
        
        // Validate version compatibility
        if (isset($data['version']) && version_compare($data['version'], '1.0.0', '<')) {
            return false;
        }
        
        $result = self::update_settings($data['settings']);
        
        if (isset($data['consent_log']) && is_array($data['consent_log'])) {
            update_option('tfm_cookie_consent_log', $data['consent_log']);
        }
        
        return $result;
    }
    
    /**
     * Reset to default settings
     */
    public static function reset_to_defaults() {
        $default_settings = array(
            'enabled' => true,
            'popup_title' => __('Cookie Consent', 'tfm-cookie-consent'),
            'popup_message' => __('This website uses cookies to ensure you get the best experience on our website.', 'tfm-cookie-consent'),
            'accept_button_text' => __('Accept All', 'tfm-cookie-consent'),
            'deny_button_text' => __('Deny All', 'tfm-cookie-consent'),
            'customize_button_text' => __('Customize', 'tfm-cookie-consent'),
            'cookie_categories' => array(
                'necessary' => array(
                    'name' => __('Necessary', 'tfm-cookie-consent'),
                    'description' => __('These cookies are essential for the website to function properly.', 'tfm-cookie-consent'),
                    'required' => true,
                    'enabled' => true
                ),
                'analytics' => array(
                    'name' => __('Analytics', 'tfm-cookie-consent'),
                    'description' => __('These cookies help us understand how visitors interact with our website.', 'tfm-cookie-consent'),
                    'required' => false,
                    'enabled' => false
                ),
                'marketing' => array(
                    'name' => __('Marketing', 'tfm-cookie-consent'),
                    'description' => __('These cookies are used to track visitors across websites for marketing purposes.', 'tfm-cookie-consent'),
                    'required' => false,
                    'enabled' => false
                ),
                'functional' => array(
                    'name' => __('Functional', 'tfm-cookie-consent'),
                    'description' => __('These cookies enable enhanced functionality and personalization.', 'tfm-cookie-consent'),
                    'required' => false,
                    'enabled' => false
                )
            ),
            'popup_position' => 'bottom',
            'popup_theme' => 'light',
            'expiry_days' => 365,
            'google_analytics' => false,
            'google_tag_manager' => false,
            'facebook_pixel' => false,
            'custom_scripts' => ''
        );
        
        return self::update_settings($default_settings);
    }
} 