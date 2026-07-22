<?php
/**
 * AJAX functionality for TFM Cookie Consent
 *
 * @package TFM_Cookie_Consent
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX class
 */
class TFM_Cookie_Consent_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_tfm_save_consent', array($this, 'save_consent'));
        add_action('wp_ajax_nopriv_tfm_save_consent', array($this, 'save_consent'));
    }
    
    /**
     * Save user consent preferences
     */
    public function save_consent() {
        check_ajax_referer('tfm_cookie_consent_nonce', 'nonce');
        
        $consent_data = array(
            'timestamp' => current_time('timestamp'),
            'categories' => array(),
            'all_accepted' => false,
            'all_denied' => false
        );
        
        // Get settings
        $settings = get_option('tfm_cookie_consent_settings', array());
        $categories = isset($settings['cookie_categories']) ? $settings['cookie_categories'] : array();
        
        // Process consent data
        if (isset($_POST['action_type'])) {
            $action_type = sanitize_text_field($_POST['action_type']);
            
            switch ($action_type) {
                case 'accept_all':
                    $consent_data['all_accepted'] = true;
                    foreach ($categories as $key => $category) {
                        $consent_data['categories'][$key] = true;
                    }
                    break;
                    
                case 'deny_all':
                    $consent_data['all_denied'] = true;
                    foreach ($categories as $key => $category) {
                        // Only allow necessary cookies when denying all
                        $consent_data['categories'][$key] = $category['required'] ?? false;
                    }
                    break;
                    
                case 'customize':
                    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
                        foreach ($_POST['categories'] as $category_key => $accepted) {
                            $category_key = sanitize_text_field($category_key);
                            if (isset($categories[$category_key])) {
                                // Required categories are always accepted
                                if ($categories[$category_key]['required'] ?? false) {
                                    $consent_data['categories'][$category_key] = true;
                                } else {
                                    $consent_data['categories'][$category_key] = (bool) $accepted;
                                }
                            }
                        }
                    }
                    break;
            }
        }
        
        // ✅ Fixed: Handle blocked cookies from JavaScript
        if (isset($_POST['blocked_cookies']) && is_array($_POST['blocked_cookies'])) {
            foreach ($_POST['blocked_cookies'] as $blocked_cookie) {
                $this->store_blocked_cookie($blocked_cookie);
            }
        }
        
        // Store consent in sessionStorage (handled by JavaScript)
        // Also store in WordPress for analytics/logging if needed
        $this->store_consent_data($consent_data);
        
        // Enable cookies after consent is given
        TFM_Cookie_Consent_Cookies::enable_cookies();
        
        // Trigger integrations based on consent
        $this->trigger_integrations($consent_data, $settings);
        
        wp_send_json_success(array(
            'message' => __('Preferences saved successfully!', 'tfm-cookie-consent'),
            'consent_data' => $consent_data
        ));
    }
    
    /**
     * Store blocked cookie
     */
    private function store_blocked_cookie($cookie) {
        $blocked_cookies = get_option('tfm_cookie_consent_blocked_cookies', array());
        $blocked_cookies[] = array(
            'cookie' => sanitize_text_field($cookie),
            'timestamp' => current_time('timestamp'),
            'session_id' => session_id(),
            'source' => 'javascript' // Mark as coming from JavaScript
        );
        
        // Keep only last 100 blocked cookies
        if (count($blocked_cookies) > 100) {
            $blocked_cookies = array_slice($blocked_cookies, -100);
        }
        
        update_option('tfm_cookie_consent_blocked_cookies', $blocked_cookies);
    }
    
    /**
     * Store consent data
     */
    private function store_consent_data($consent_data) {
        // Store in WordPress options for analytics/logging
        $consent_log = get_option('tfm_cookie_consent_log', array());
        $consent_log[] = array(
            'timestamp' => $consent_data['timestamp'],
            'ip' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'categories' => $consent_data['categories'],
            'all_accepted' => $consent_data['all_accepted'],
            'all_denied' => $consent_data['all_denied']
        );
        
        // Keep only last 1000 entries
        if (count($consent_log) > 1000) {
            $consent_log = array_slice($consent_log, -1000);
        }
        
        update_option('tfm_cookie_consent_log', $consent_log);
    }
    
    /**
     * Trigger integrations based on consent
     */
    private function trigger_integrations($consent_data, $settings) {
        $integrations = isset($settings['integrations']) ? $settings['integrations'] : array();
        
        // Google Analytics
        if (isset($integrations['google_analytics']) && $integrations['google_analytics']) {
            if (isset($consent_data['categories']['analytics']) && $consent_data['categories']['analytics']) {
                $this->enable_google_analytics();
            }
        }
        
        // Google Tag Manager
        if (isset($integrations['google_tag_manager']) && $integrations['google_tag_manager']) {
            if ($consent_data['all_accepted'] || (isset($consent_data['categories']['analytics']) && $consent_data['categories']['analytics'])) {
                $this->enable_google_tag_manager();
            }
        }
        
        // Facebook Pixel
        if (isset($integrations['facebook_pixel']) && $integrations['facebook_pixel']) {
            if (isset($consent_data['categories']['marketing']) && $consent_data['categories']['marketing']) {
                $this->enable_facebook_pixel();
            }
        }
        
        // Custom scripts
        if (!empty($settings['custom_scripts'])) {
            $this->execute_custom_scripts($consent_data, $settings['custom_scripts']);
        }
    }
    
    /**
     * Enable Google Analytics
     */
    private function enable_google_analytics() {
        // This would typically involve setting a flag or cookie
        // that allows Google Analytics to load
        if (!headers_sent()) {
            setcookie('tfm_analytics_enabled', '1', time() + (365 * 24 * 60 * 60), '/');
        }
    }
    
    /**
     * Enable Google Tag Manager
     */
    private function enable_google_tag_manager() {
        if (!headers_sent()) {
            setcookie('tfm_gtm_enabled', '1', time() + (365 * 24 * 60 * 60), '/');
        }
    }
    
    /**
     * Enable Facebook Pixel
     */
    private function enable_facebook_pixel() {
        if (!headers_sent()) {
            setcookie('tfm_facebook_enabled', '1', time() + (365 * 24 * 60 * 60), '/');
        }
    }
    
    /**
     * Execute custom scripts
     */
    private function execute_custom_scripts($consent_data, $custom_scripts) {
        // Replace placeholders in custom scripts
        $script = $custom_scripts;
        
        foreach ($consent_data['categories'] as $category => $accepted) {
            $script = str_replace('{category:' . $category . '}', $accepted ? 'true' : 'false', $script);
            $script = str_replace('{category_' . $category . '}', $accepted ? 'true' : 'false', $script);
        }
        
        // Store the processed script for frontend execution
        update_option('tfm_cookie_consent_custom_script', $script);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
} 