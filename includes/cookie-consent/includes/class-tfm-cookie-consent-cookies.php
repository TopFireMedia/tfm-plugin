<?php
/**
 * Cookie functionality for TFM Cookie Consent
 *
 * @package TFM_Cookie_Consent
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cookies class
 */
class TFM_Cookie_Consent_Cookies {
    
    /**
     * Cookie name for consent storage
     */
    const CONSENT_COOKIE_NAME = 'tfm_cookie_consent';
    
    /**
     * Blocked cookies storage
     */
    const BLOCKED_COOKIES_OPTION = 'tfm_cookie_consent_blocked_cookies';
    
    /**
     * Constructor - initialize cookie blocking
     */
    public function __construct() {
        
        // Check if plugin is enabled
        $settings = get_option('tfm_cookie_consent_settings', array());
        if (!isset($settings['enabled']) || !$settings['enabled']) {
            return;
        }
        
        // Initialize cookie blocking immediately
        $this->init_cookie_blocking();
        
        // Hook into WordPress cookie setting
        add_filter('wp_headers', array($this, 'filter_cookie_headers'), 10, 2);
        
    }
    
    /**
     * Initialize cookie blocking
     */
    public function init_cookie_blocking() {
        // Debug: Check if this method is being called
        
        // Add cookie blocking script as early as possible
        add_action('send_headers', array($this, 'block_cookies_headers'));
        add_action('wp_head', array($this, 'add_cookie_blocking_script_early'), 1);
    }
    
    /**
     * Block cookies in headers
     */
    public function block_cookies_headers() {
        if (!self::has_consent()) {
            // Set headers to prevent cookie setting
            header('Set-Cookie: tfm_cookie_blocked=1; Path=/; HttpOnly; SameSite=Strict');
        }
    }
    
    /**
     * Filter cookie headers to block non-necessary cookies
     */
    public function filter_cookie_headers($headers, $wp) {
        if (!self::has_consent() && isset($headers['Set-Cookie'])) {
            $filtered_cookies = array();
            $cookies = is_array($headers['Set-Cookie']) ? $headers['Set-Cookie'] : array($headers['Set-Cookie']);
            
            foreach ($cookies as $cookie) {
                // Allow necessary cookies
                if (strpos($cookie, 'tfm_cookie_consent') !== false ||
                    strpos($cookie, 'wordpress_') !== false ||
                    strpos($cookie, 'wp-') !== false ||
                    strpos($cookie, 'PHPSESSID') !== false ||
                    strpos($cookie, 'tfm_cookie_blocked') !== false) {
                    $filtered_cookies[] = $cookie;
                } else {
                    // Store blocked cookie for later
                    $this->store_blocked_cookie($cookie);
                }
            }
            
            if (!empty($filtered_cookies)) {
                $headers['Set-Cookie'] = count($filtered_cookies) === 1 ? $filtered_cookies[0] : $filtered_cookies;
            } else {
                unset($headers['Set-Cookie']);
            }
        }
        
        return $headers;
    }
    
    /**
     * Categorize cookie based on name patterns
     */
    public static function categorize_cookie($cookie_name) {
        $patterns = array(
            'analytics' => array('_ga', '_gid', '_gat', 'analytics', 'gtag', 'gtm', 'google', 'googletagmanager'),
            'marketing' => array('_fbp', 'marketing', 'ads', 'pixel', 'facebook', 'fb_', 'adwords', 'bing'),
            'functional' => array('functional', 'preferences', 'settings', 'user_', 'custom_', 'theme_'),
            'necessary' => array('wordpress_', 'wp-', 'PHPSESSID', 'tfm_', 'session', 'csrf')
        );
        
        foreach ($patterns as $category => $pattern_list) {
            foreach ($pattern_list as $pattern) {
                if (stripos($cookie_name, $pattern) !== false) {
                    return $category;
                }
            }
        }
        return 'unknown';
    }
    
    /**
     * Cookie discovery and tracking
     */
    public static function discover_cookies() {
        $discovered_cookies = get_option('tfm_cookie_consent_discovered_cookies', array());
        $current_cookies = array();
        
        // Get all cookies from $_COOKIE
        if (!empty($_COOKIE)) {
            foreach ($_COOKIE as $name => $value) {
                $category = self::categorize_cookie($name);
                $current_cookies[] = array(
                    'name' => $name,
                    'value' => $value,
                    'category' => $category,
                    'discovered_at' => current_time('timestamp'),
                    'source' => 'browser'
                );
            }
        }
        
        // Merge with existing discovered cookies
        foreach ($current_cookies as $cookie) {
            $found = false;
            foreach ($discovered_cookies as $existing) {
                if ($existing['name'] === $cookie['name']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $discovered_cookies[] = $cookie;
            }
        }
        
        // Keep only last 500 discovered cookies
        if (count($discovered_cookies) > 500) {
            $discovered_cookies = array_slice($discovered_cookies, -500);
        }
        
        update_option('tfm_cookie_consent_discovered_cookies', $discovered_cookies);
        return $discovered_cookies;
    }
    
    /**
     * Get cookie discovery statistics
     */
    public static function get_cookie_discovery_statistics() {
        $discovered_cookies = get_option('tfm_cookie_consent_discovered_cookies', array());
        $stats = array(
            'total_discovered' => count($discovered_cookies),
            'by_category' => array(
                'analytics' => 0,
                'marketing' => 0,
                'functional' => 0,
                'necessary' => 0,
                'unknown' => 0
            ),
            'by_source' => array(
                'browser' => 0,
                'javascript' => 0,
                'php' => 0
            )
        );
        
        foreach ($discovered_cookies as $cookie) {
            $category = $cookie['category'];
            $source = isset($cookie['source']) ? $cookie['source'] : 'browser';
            
            if (isset($stats['by_category'][$category])) {
                $stats['by_category'][$category]++;
            }
            
            if (isset($stats['by_source'][$source])) {
                $stats['by_source'][$source]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get cookie statistics
     */
    public static function get_cookie_statistics() {
        $blocked_cookies = get_option('tfm_cookie_consent_blocked_cookies', array());
        $cookie_stats = array(
            'total_blocked' => count($blocked_cookies),
            'by_category' => array(
                'analytics' => 0,
                'marketing' => 0,
                'functional' => 0,
                'necessary' => 0,
                'unknown' => 0
            ),
            'by_source' => array(
                'javascript' => 0,
                'php' => 0
            )
        );
        
        foreach ($blocked_cookies as $blocked_cookie) {
            $category = self::categorize_cookie($blocked_cookie['cookie']);
            $cookie_stats['by_category'][$category]++;
            
            $source = isset($blocked_cookie['source']) ? $blocked_cookie['source'] : 'php';
            $cookie_stats['by_source'][$source]++;
        }
        
        return $cookie_stats;
    }
    
    /**
     * Store blocked cookie for later setting
     */
    private function store_blocked_cookie($cookie) {
        $blocked_cookies = get_option(self::BLOCKED_COOKIES_OPTION, array());
        $blocked_cookies[] = array(
            'cookie' => $cookie,
            'timestamp' => current_time('timestamp'),
            'session_id' => session_id()
        );
        
        // Keep only last 100 blocked cookies
        if (count($blocked_cookies) > 100) {
            $blocked_cookies = array_slice($blocked_cookies, -100);
        }
        
        update_option(self::BLOCKED_COOKIES_OPTION, $blocked_cookies);
    }
    
    /**
     * Add JavaScript to block cookies early
     */
    public function add_cookie_blocking_script_early() {
        ?>
        <script>
        // Only initialize once
        if (window.tfmCookieBlockingInitialized) {
            // Already initialized, exit
        } else {
            console.log('TFM Cookie Consent: Initializing cookie blocking...');
            // Block cookies until consent is given
            (function() {
            // Check if consent exists and what type
            var hasConsent = false;
            var consentType = 'none';
            try {
                var stored = sessionStorage.getItem('tfm_cookie_consent');
                if (stored) {
                    var consentData = JSON.parse(stored);
                    if (consentData && consentData.timestamp) {
                        hasConsent = true;
                        
                        // Determine consent type
                        if (consentData.all_accepted) {
                            consentType = 'accept_all';
                        } else if (consentData.all_denied) {
                            consentType = 'deny_all';
                        } else {
                            consentType = 'customize';
                        }
                    }
                }
            } catch (e) {
                if (window.tfmCookieConsentDebug) {
                    console.warn('Error checking consent:', e);
                }
            }
            
            if (hasConsent && consentType === 'accept_all') {
                if (window.tfmCookieConsentDebug) {
                    console.log('TFM Cookie Consent: Accept all consent found, not blocking cookies');
                }
                return;
            } else if (hasConsent && consentType === 'deny_all') {
                if (window.tfmCookieConsentDebug) {
                    console.log('TFM Cookie Consent: Deny all consent found, keeping cookies blocked');
                }
            } else if (hasConsent && consentType === 'customize') {
                if (window.tfmCookieConsentDebug) {
                    console.log('TFM Cookie Consent: Customize consent found, will use selective blocking');
                }
                // Store accepted categories for selective mode
                if (consentData && consentData.categories) {
                    window.tfmAcceptedCategories = consentData.categories;
                    window.tfmCookieBlockingSelective = true;
                }
            } else {
                if (window.tfmCookieConsentDebug) {
                    console.log('TFM Cookie Consent: No consent found, blocking cookies');
                }
            }
            
            console.log('TFM Cookie Consent: No consent found, blocking cookies');
            
            // Store original cookie setter
            var originalCookie = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
            window.tfmOriginalCookieGetter = originalCookie.get;
            window.tfmOriginalCookieSetter = originalCookie.set;
            
            // Create a filtered getter that excludes blocked cookies
            var filteredGetter = function() {
                var allCookies = window.tfmOriginalCookieGetter.call(this);
                if (!window.tfmBlockedCookies || window.tfmBlockedCookies.length === 0) {
                    return allCookies;
                }
                
                // Filter out cookies that are in our blocked list
                var cookieArray = allCookies.split(';');
                var filteredCookies = [];
                
                for (var i = 0; i < cookieArray.length; i++) {
                    var cookie = cookieArray[i].trim();
                    var cookieName = cookie.split('=')[0];
                    var isBlocked = false;
                    
                    for (var j = 0; j < window.tfmBlockedCookies.length; j++) {
                        var blockedCookie = window.tfmBlockedCookies[j];
                        var blockedName = blockedCookie.split('=')[0];
                        if (cookieName === blockedName) {
                            isBlocked = true;
                            break;
                        }
                    }
                    
                    if (!isBlocked) {
                        filteredCookies.push(cookie);
                    }
                }
                
                return filteredCookies.join('; ');
            };
            
            Object.defineProperty(document, 'cookie', {
                get: function() {
                    return filteredGetter.call(this);
                },
                set: function(value) {
                    // Check if cookie blocking is disabled
                    if (window.tfmCookieBlockingDisabled) {
                        if (window.tfmCookieConsentDebug) {
                            console.log('TFM Cookie Consent: Cookie blocking disabled, allowing cookie:', value);
                        }
                        return window.tfmOriginalCookieSetter.call(this, value);
                    }
                    
                    // Check if cookie blocking is in selective mode
                    if (window.tfmCookieBlockingSelective && window.tfmAcceptedCategories) {
                        // Check if this cookie belongs to an accepted category
                        var cookieName = value.split('=')[0];
                        var isAllowed = false;
                        
                        // Check if cookie name matches any accepted category patterns
                        for (var category in window.tfmAcceptedCategories) {
                            if (window.tfmAcceptedCategories[category]) {
                                // Add category-specific cookie name patterns here
                                if (category === 'analytics' && (cookieName.indexOf('_ga') !== -1 || cookieName.indexOf('_gid') !== -1 || cookieName.indexOf('_gat') !== -1 || cookieName.indexOf('analytics') !== -1 || cookieName.indexOf('gtag') !== -1 || cookieName.indexOf('gtm') !== -1 || cookieName.indexOf('google') !== -1 || cookieName.indexOf('googletagmanager') !== -1)) {
                                    isAllowed = true;
                                    break;
                                } else if (category === 'marketing' && (cookieName.indexOf('_fbp') !== -1 || cookieName.indexOf('marketing') !== -1 || cookieName.indexOf('ads') !== -1 || cookieName.indexOf('pixel') !== -1 || cookieName.indexOf('facebook') !== -1 || cookieName.indexOf('fb_') !== -1 || cookieName.indexOf('adwords') !== -1 || cookieName.indexOf('bing') !== -1)) {
                                    isAllowed = true;
                                    break;
                                } else if (category === 'functional' && (cookieName.indexOf('functional') !== -1 || cookieName.indexOf('preferences') !== -1 || cookieName.indexOf('settings') !== -1 || cookieName.indexOf('user_') !== -1 || cookieName.indexOf('custom_') !== -1 || cookieName.indexOf('theme_') !== -1)) {
                                    isAllowed = true;
                                    break;
                                }
                            }
                        }
                        
                        if (isAllowed) {
                            if (window.tfmCookieConsentDebug) {
                                console.log('TFM Cookie Consent: Allowing cookie for accepted category:', value);
                            }
                            return window.tfmOriginalCookieSetter.call(this, value);
                        }
                    }
                    
                    // Allow necessary cookies only
                    if (value.indexOf('tfm_cookie_consent') !== -1 || 
                        value.indexOf('wordpress_') !== -1 || 
                        value.indexOf('wp-') !== -1 ||
                        value.indexOf('PHPSESSID') !== -1 ||
                        value.indexOf('tfm_cookie_blocked') !== -1) {
                        if (window.tfmCookieConsentDebug) {
                            console.log('TFM Cookie Consent: Allowing necessary cookie:', value);
                        }
                        return window.tfmOriginalCookieSetter.call(this, value);
                    }
                    
                    // Block other cookies and store for later
                    if (window.tfmCookieConsentDebug) {
                        console.log('TFM Cookie Consent: Blocking cookie:', value);
                    }
                    document.blockCookie(value);
                    return; // Don't call the original setter
                }
            });
            
            // Store blocked cookies
            document.blockCookie = function(cookieValue) {
                if (!window.tfmBlockedCookies) {
                    window.tfmBlockedCookies = [];
                }
                window.tfmBlockedCookies.push(cookieValue);
                if (window.tfmCookieConsentDebug) {
                    console.log('TFM Cookie Consent: Stored blocked cookie:', cookieValue);
                }
            };
            
            // Method to set blocked cookies after consent
            document.setBlockedCookies = function() {
                if (window.tfmBlockedCookies && window.tfmBlockedCookies.length > 0) {
                    if (window.tfmCookieConsentDebug) {
                        console.log('TFM Cookie Consent: Setting', window.tfmBlockedCookies.length, 'blocked cookies');
                    }
                    window.tfmBlockedCookies.forEach(function(cookie) {
                        window.tfmOriginalCookieSetter.call(document, cookie);
                    });
                    window.tfmBlockedCookies = [];
                }
            };
            
            // Method to set blocked cookies for specific categories
            document.setBlockedCookiesForCategories = function(acceptedCategories) {
                if (window.tfmBlockedCookies && window.tfmBlockedCookies.length > 0) {
                    if (window.tfmCookieConsentDebug) {
                        console.log('TFM Cookie Consent: Setting blocked cookies for categories:', acceptedCategories);
                    }
                    
                    var cookiesToSet = [];
                    var cookiesToKeep = [];
                    
                    window.tfmBlockedCookies.forEach(function(cookie) {
                        var cookieName = cookie.split('=')[0];
                        var shouldSet = false;
                        
                        // Check if cookie belongs to an accepted category
                        for (var category in acceptedCategories) {
                            if (acceptedCategories[category]) {
                                if (category === 'analytics' && (cookieName.indexOf('_ga') !== -1 || cookieName.indexOf('_gid') !== -1 || cookieName.indexOf('analytics') !== -1)) {
                                    shouldSet = true;
                                    break;
                                } else if (category === 'marketing' && (cookieName.indexOf('_fbp') !== -1 || cookieName.indexOf('marketing') !== -1)) {
                                    shouldSet = true;
                                    break;
                                } else if (category === 'functional' && (cookieName.indexOf('functional') !== -1)) {
                                    shouldSet = true;
                                    break;
                                }
                            }
                        }
                        
                        if (shouldSet) {
                            cookiesToSet.push(cookie);
                        } else {
                            cookiesToKeep.push(cookie);
                        }
                    });
                    
                    // Set cookies for accepted categories
                    cookiesToSet.forEach(function(cookie) {
                        window.tfmOriginalCookieSetter.call(document, cookie);
                    });
                    
                    // Keep remaining cookies blocked
                    window.tfmBlockedCookies = cookiesToKeep;
                    
                    if (window.tfmCookieConsentDebug) {
                        console.log('TFM Cookie Consent: Set', cookiesToSet.length, 'cookies, kept', cookiesToKeep.length, 'blocked');
                    }
                }
            };
            
            if (window.tfmCookieConsentDebug) {
                console.log('TFM Cookie Consent: Cookie blocking initialized');
            }
            window.tfmCookieBlockingInitialized = true;
        })();
        </script>
        <?php
    }
    

    
    /**
     * Check if user has given consent
     */
    public static function has_consent() {
        return isset($_COOKIE[self::CONSENT_COOKIE_NAME]) || 
               (isset($_SESSION['tfm_cookie_consent']) && $_SESSION['tfm_cookie_consent']);
    }
    
    /**
     * Get user consent data
     */
    public static function get_consent_data() {
        $consent_data = null;
        
        // Check sessionStorage first (primary method)
        if (isset($_COOKIE[self::CONSENT_COOKIE_NAME])) {
            $consent_data = json_decode(stripslashes($_COOKIE[self::CONSENT_COOKIE_NAME]), true);
        }
        
        // Fallback to session
        if (!$consent_data && isset($_SESSION['tfm_cookie_consent'])) {
            $consent_data = $_SESSION['tfm_cookie_consent'];
        }
        
        return $consent_data;
    }
    
    /**
     * Check if user has consented to a specific category
     */
    public static function has_category_consent($category) {
        $consent_data = self::get_consent_data();
        
        if (!$consent_data) {
            return false;
        }
        
        // Check if all cookies were accepted
        if (isset($consent_data['all_accepted']) && $consent_data['all_accepted']) {
            return true;
        }
        
        // Check specific category
        if (isset($consent_data['categories'][$category])) {
            return $consent_data['categories'][$category];
        }
        
        return false;
    }
    
    /**
     * Set consent cookie
     */
    public static function set_consent_cookie($consent_data, $expiry_days = 365) {
        $expiry = time() + ($expiry_days * 24 * 60 * 60);
        
        // Set cookie
        if (!headers_sent()) {
            setcookie(
                self::CONSENT_COOKIE_NAME,
                json_encode($consent_data),
                $expiry,
                '/',
                '',
                is_ssl(),
                true // HttpOnly
            );
        }
        
        // Also store in session as backup
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['tfm_cookie_consent'] = $consent_data;
        }
    }
    
    /**
     * Clear consent cookie
     */
    public static function clear_consent_cookie() {
        if (!headers_sent()) {
            setcookie(self::CONSENT_COOKIE_NAME, '', time() - 3600, '/');
        }
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['tfm_cookie_consent']);
        }
    }
    
    /**
     * Check if consent has expired
     */
    public static function is_consent_expired() {
        $consent_data = self::get_consent_data();
        
        if (!$consent_data || !isset($consent_data['timestamp'])) {
            return true;
        }
        
        $settings = TFM_Cookie_Consent_Settings::get_settings();
        $expiry_days = isset($settings['expiry_days']) ? absint($settings['expiry_days']) : 365;
        $expiry_timestamp = $consent_data['timestamp'] + ($expiry_days * 24 * 60 * 60);
        
        return time() > $expiry_timestamp;
    }
    
    /**
     * Get consent statistics for current user
     */
    public static function get_user_consent_stats() {
        $consent_data = self::get_consent_data();
        
        if (!$consent_data) {
            return array(
                'has_consent' => false,
                'consent_type' => 'none',
                'categories_accepted' => 0,
                'categories_denied' => 0,
                'total_categories' => 0
            );
        }
        
        $stats = array(
            'has_consent' => true,
            'consent_type' => 'customize',
            'categories_accepted' => 0,
            'categories_denied' => 0,
            'total_categories' => 0
        );
        
        if (isset($consent_data['all_accepted']) && $consent_data['all_accepted']) {
            $stats['consent_type'] = 'accept_all';
        } elseif (isset($consent_data['all_denied']) && $consent_data['all_denied']) {
            $stats['consent_type'] = 'deny_all';
        }
        
        if (isset($consent_data['categories'])) {
            $stats['total_categories'] = count($consent_data['categories']);
            foreach ($consent_data['categories'] as $accepted) {
                if ($accepted) {
                    $stats['categories_accepted']++;
                } else {
                    $stats['categories_denied']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Check if analytics cookies are allowed
     */
    public static function analytics_allowed() {
        return self::has_category_consent('analytics');
    }
    
    /**
     * Check if marketing cookies are allowed
     */
    public static function marketing_allowed() {
        return self::has_category_consent('marketing');
    }
    
    /**
     * Check if functional cookies are allowed
     */
    public static function functional_allowed() {
        return self::has_category_consent('functional');
    }
    
    /**
     * Check if necessary cookies are allowed (always true)
     */
    public static function necessary_allowed() {
        return true; // Necessary cookies are always allowed
    }
    
    /**
     * Get all allowed categories
     */
    public static function get_allowed_categories() {
        $consent_data = self::get_consent_data();
        
        if (!$consent_data) {
            return array('necessary'); // Only necessary cookies allowed by default
        }
        
        $allowed = array('necessary'); // Necessary cookies are always allowed
        
        if (isset($consent_data['all_accepted']) && $consent_data['all_accepted']) {
            // All categories allowed
            $settings = TFM_Cookie_Consent_Settings::get_settings();
            $categories = isset($settings['cookie_categories']) ? $settings['cookie_categories'] : array();
            foreach (array_keys($categories) as $category) {
                if ($category !== 'necessary') {
                    $allowed[] = $category;
                }
            }
        } elseif (isset($consent_data['categories'])) {
            foreach ($consent_data['categories'] as $category => $accepted) {
                if ($accepted && $category !== 'necessary') {
                    $allowed[] = $category;
                }
            }
        }
        
        return $allowed;
    }
    
    /**
     * Get all denied categories
     */
    public static function get_denied_categories() {
        $consent_data = self::get_consent_data();
        
        if (!$consent_data) {
            $settings = TFM_Cookie_Consent_Settings::get_settings();
            $categories = isset($settings['cookie_categories']) ? $settings['cookie_categories'] : array();
            $denied = array();
            foreach (array_keys($categories) as $category) {
                if ($category !== 'necessary') {
                    $denied[] = $category;
                }
            }
            return $denied;
        }
        
        $denied = array();
        
        if (isset($consent_data['all_denied']) && $consent_data['all_denied']) {
            // All categories denied except necessary
            $settings = TFM_Cookie_Consent_Settings::get_settings();
            $categories = isset($settings['cookie_categories']) ? $settings['cookie_categories'] : array();
            foreach (array_keys($categories) as $category) {
                if ($category !== 'necessary') {
                    $denied[] = $category;
                }
            }
        } elseif (isset($consent_data['categories'])) {
            foreach ($consent_data['categories'] as $category => $accepted) {
                if (!$accepted && $category !== 'necessary') {
                    $denied[] = $category;
                }
            }
        }
        
        return $denied;
    }
    
    /**
     * Check if Google Analytics should be loaded
     */
    public static function should_load_google_analytics() {
        $settings = TFM_Cookie_Consent_Settings::get_settings();
        $integrations = isset($settings['integrations']) ? $settings['integrations'] : array();
        
        return isset($integrations['google_analytics']) && 
               $integrations['google_analytics'] && 
               self::analytics_allowed();
    }
    
    /**
     * Check if Google Tag Manager should be loaded
     */
    public static function should_load_google_tag_manager() {
        $settings = TFM_Cookie_Consent_Settings::get_settings();
        $integrations = isset($settings['integrations']) ? $settings['integrations'] : array();
        
        return isset($integrations['google_tag_manager']) && 
               $integrations['google_tag_manager'] && 
               (self::analytics_allowed() || self::marketing_allowed());
    }
    
    /**
     * Check if Facebook Pixel should be loaded
     */
    public static function should_load_facebook_pixel() {
        $settings = TFM_Cookie_Consent_Settings::get_settings();
        $integrations = isset($settings['integrations']) ? $settings['integrations'] : array();
        
        return isset($integrations['facebook_pixel']) && 
               $integrations['facebook_pixel'] && 
               self::marketing_allowed();
    }
    
    /**
     * Enable cookie setting after consent
     */
    public static function enable_cookies() {
        // Set blocked cookies from server
        self::set_blocked_cookies();
        
        // Add script to enable cookies
        add_action('wp_footer', array(__CLASS__, 'enable_cookies_script'));
    }
    
    /**
     * Set blocked cookies after consent
     */
    public static function set_blocked_cookies() {
        $blocked_cookies = get_option(self::BLOCKED_COOKIES_OPTION, array());
        $current_session = session_id();
        
        foreach ($blocked_cookies as $index => $blocked_cookie) {
            if ($blocked_cookie['session_id'] === $current_session) {
                // Set the cookie
                if (!headers_sent()) {
                    header('Set-Cookie: ' . $blocked_cookie['cookie']);
                }
                
                // Remove from blocked list
                unset($blocked_cookies[$index]);
            }
        }
        
        // Update blocked cookies list
        update_option(self::BLOCKED_COOKIES_OPTION, array_values($blocked_cookies));
    }
    
    /**
     * Add JavaScript to enable cookies
     */
    public static function enable_cookies_script() {
        ?>
        <script>
        // Enable cookies after consent
        if (typeof document.setBlockedCookies === 'function') {
            document.setBlockedCookies();
        }
        
        // Restore original cookie setter
        if (window.tfmOriginalCookieSetter) {
            Object.defineProperty(document, 'cookie', {
                get: function() {
                    return window.tfmOriginalCookieGetter.call(this);
                },
                set: function(value) {
                    return window.tfmOriginalCookieSetter.call(this, value);
                }
            });
        }
        </script>
        <?php
    }
} 