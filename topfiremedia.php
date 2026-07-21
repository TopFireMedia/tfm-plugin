<?php
/**
 * Plugin Name: TFM Custom Functions
 * Plugin URI: https://topfiremedia.com
 * Description: A comprehensive plugin for TFM functionality including logging, video optimization, and more.
 * Version: 3.14.3
 * Author: TopFireMedia
 * Author URI: https://topfiremedia.com
 * Text Domain: topfiremedia
 * Domain Path: /languages

 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

// HTML Sitemap Generation Functions
function tfm_sitemap_is_enabled() {
    $settings = tfm_load_settings();
    return isset($settings['sitemap_enabled']) && $settings['sitemap_enabled'];
}

function tfm_sitemap_get_cache_key($args = []) {
    return 'tfm_sitemap_' . md5(serialize($args));
}

function tfm_sitemap_get_cached($args = []) {
    if (!tfm_sitemap_is_enabled()) {
        return false;
    }

    $settings = tfm_load_settings();
    $cache_key = tfm_sitemap_get_cache_key($args);
    $cache_timeout = isset($settings['sitemap_cache_timeout']) ? $settings['sitemap_cache_timeout'] : 3600;

    return get_transient($cache_key);
}

function tfm_sitemap_set_cached($content, $args = []) {
    if (!tfm_sitemap_is_enabled()) {
        return;
    }

    $settings = tfm_load_settings();
    $cache_key = tfm_sitemap_get_cache_key($args);
    $cache_timeout = isset($settings['sitemap_cache_timeout']) ? $settings['sitemap_cache_timeout'] : 3600;

    set_transient($cache_key, $content, $cache_timeout);
}

function tfm_sitemap_clear_cache() {
    global $wpdb;

    // Clear all tfm sitemap transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tfm_sitemap_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tfm_sitemap_%'");
}


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('TFM_PLUGIN_VERSION', '3.14.3');
define('TFM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TFM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Configure WordPress Post Revisions (must be defined very early)
if (!defined('WP_POST_REVISIONS')) {
    // Load settings directly from database to avoid function calls this early
    $tfm_settings = get_option('tfm_plugin_settings', []);

    // Temporarily remove debug logging
    // error_log('TFM Revisions Debug - Raw settings: ' . print_r($tfm_settings, true));

    if (isset($tfm_settings['disable_wp_revisions']) && $tfm_settings['disable_wp_revisions']) {
        define('WP_POST_REVISIONS', 0); // Disable revisions entirely
    } elseif (isset($tfm_settings['wp_post_revisions_limit'])) {
        $limit = absint($tfm_settings['wp_post_revisions_limit']);
        define('WP_POST_REVISIONS', $limit);
    } else {
        // Check if setting exists but is 0 (which would be falsy)
        if (array_key_exists('wp_post_revisions_limit', $tfm_settings)) {
            $limit = absint($tfm_settings['wp_post_revisions_limit']);
            define('WP_POST_REVISIONS', $limit);
        } else {
            define('WP_POST_REVISIONS', 5); // Default WordPress behavior
        }
    }
}

// Include required files
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-activation-checks.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-file-logger.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-logging-hooks.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-updater.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-video-defer.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-svg-sanitizer.php';


// Activation hook
register_activation_hook(__FILE__, 'tfm_activate_plugin');

// Disable jQuery Migrate
function tfm_disable_jquery_migrate($scripts) {
    $settings = tfm_load_settings();
    if (isset($settings['disable_jquery_migrate']) && $settings['disable_jquery_migrate']) {
        if (!is_admin()) { // Only on frontend
            $scripts->remove('jquery');
            $scripts->add('jquery-core', false, array('jquery-core'), '');
        }
        $scripts->remove('jquery-migrate');
    }
}
add_action('wp_default_scripts', 'tfm_disable_jquery_migrate');

// Disable oEmbeds
function tfm_disable_oembeds() {
    $settings = tfm_load_settings();
    if (isset($settings['disable_oembeds']) && $settings['disable_oembeds']) {
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        remove_filter('the_content', 'wp_oembed_remove_endpoint');
        remove_filter('wp_embed_handler_html', 'wp_embed_handler_html');
        remove_filter('embed_oembed_html', 'wp_embed_handler_html');
        add_filter('embed_oembed_html', '__return_false', 9999);
    }
}
add_action('init', 'tfm_disable_oembeds');

// Additional revisions control via filter (backup to constant)
function tfm_revisions_to_keep($num, $post) {
    $settings = get_option('tfm_plugin_settings', []);

    if (isset($settings['disable_wp_revisions']) && $settings['disable_wp_revisions']) {
        return 0; // Disable revisions entirely
    } elseif (isset($settings['wp_post_revisions_limit'])) {
        return absint($settings['wp_post_revisions_limit']);
    }

    return $num; // Return original value if no setting
}
add_filter('wp_revisions_to_keep', 'tfm_revisions_to_keep', 10, 2);

// Disable WordPress Emojis\nfunction tfm_disable_emojis() {\n    $settings = tfm_load_settings();\n    if (isset($settings[\'disable_emojis\']) && $settings[\'disable_emojis\']) {\n        remove_action(\'wp_head\', \'print_emoji_detection_script\', 7);\n        remove_action(\'admin_print_scripts\', \'print_emoji_detection_script\');\n        remove_action(\'wp_print_styles\', \'print_emoji_styles\');\n        remove_action(\'admin_print_styles\', \'print_emoji_styles\');\n        remove_filter(\'the_content_feed\', \'wp_staticize_emoji\');\n        remove_filter(\'comment_text_rss\', \'wp_staticize_emoji\');\n        remove_filter(\'wp_mail\', \'wp_staticize_emoji_for_email\');\n        add_filter(\'tiny_mce_plugins\', \'tfm_disable_emojis_tinymce\');\n        add_filter(\'wp_resource_hints\', \'tfm_disable_emojis_remove_dns_prefetch\', 10, 2);\n    }\n}\nadd_action(\'init\', \'tfm_disable_emojis\');\n\nfunction tfm_disable_emojis_tinymce($plugins) {\n    if (is_array($plugins)) {\n        return array_diff($plugins, [\'wpemoji\']);\n    }\n    return $plugins;\n}\n\nfunction tfm_disable_emojis_remove_dns_prefetch($urls, $relation_type) {\n    if (\'dns-prefetch\' === $relation_type) {\n        // Strip out any URLs that are related to emojis.\n        $emoji_svg_url_find = \'/(^\\/\/s\\.w\\.org\\/images\\/core\\/emoji\\/(.+?)\\/svg\\/)/i\';\n        foreach ($urls as $key => $url) {\n            if (preg_match($emoji_svg_url_find, $url)) {\n                unset($urls[$key]);\n            }\n        }\n    }\n    return $urls;\n}

/**
 * Plugin activation function
 */
function tfm_activate_plugin() {
    // Prevent any output
    ob_start();
    
    try {
        $activation_checks = new TFM_Activation_Checks();
        
        if (!$activation_checks->run_checks()) {
            // Get all errors
            $errors = $activation_checks->get_errors();
            
            // Deactivate the plugin
            deactivate_plugins(plugin_basename(__FILE__));
            
            // Clear any output
            ob_end_clean();
            
            // Die with error message
            wp_die(
                '<h1>Plugin Activation Error</h1>' .
                '<p>The following issues were found:</p>' .
                '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>' .
                '<p>Please resolve these issues and try activating the plugin again.</p>',
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }
        
        // Clear any output
        ob_end_clean();
        
    } catch (Exception $e) {
        // Clear any output
        ob_end_clean();
        
        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Die with error message
        wp_die(
            '<h1>Plugin Activation Error</h1>' .
            '<p>An unexpected error occurred during activation:</p>' .
            '<p>' . esc_html($e->getMessage()) . '</p>' .
            '<p>Please try activating the plugin again.</p>',
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
}

// Initialize components after activation
function tfm_init_components() {
    try {
        // Check if required classes exist
        if (!class_exists('TFM_File_Logger')) {
            throw new Exception('Required class TFM_File_Logger not found');
        }
        if (!class_exists('TFM_Logging_Hooks')) {
            throw new Exception('Required class TFM_Logging_Hooks not found');
        }
        if (!class_exists('TFM_Video_Defer')) {
            throw new Exception('Required class TFM_Video_Defer not found');
        }

        // Initialize logger
        global $tfm_logger;
        $tfm_logger = new TFM_File_Logger();
        if (!method_exists($tfm_logger, 'init')) {
            throw new Exception('Required method init() not found in TFM_File_Logger');
        }
        $tfm_logger->init();

        // Initialize logging hooks
        global $tfm_logging_hooks;
        $tfm_logging_hooks = new TFM_Logging_Hooks($tfm_logger);
        if (!method_exists($tfm_logging_hooks, 'init')) {
            throw new Exception('Required method init() not found in TFM_Logging_Hooks');
        }
        $tfm_logging_hooks->init();

        // Initialize video defer
        global $tfm_video_defer;
        $tfm_video_defer = new TFM_Video_Defer();

        // Initialize updater
        if (!class_exists('TFM_Updater')) {
            throw new Exception('Required class TFM_Updater not found');
        }
        TFM_Updater::getInstance();
    } catch (Exception $e) {
        // Log the error
        error_log('TFM Plugin Error: ' . $e->getMessage());
        
        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Add admin notice
        add_action('admin_notices', function() use ($e) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong>TFM Custom Functions Error:</strong> 
                    <?php echo esc_html($e->getMessage()); ?>
                    <br>
                    The plugin has been deactivated to prevent further issues.
                </p>
            </div>
            <?php
        });
    }
}
add_action('plugins_loaded', 'tfm_init_components', 20);

// Initialize Elementor integration when Elementor is loaded
function tfm_init_elementor_integration() {
    if (!class_exists('\Elementor\Plugin')) {
        return;
    }

    if (!class_exists('TFM_Elementor')) {
        require_once TFM_PLUGIN_DIR . 'includes/class-tfm-elementor.php';
    }

    TFM_Elementor::get_instance();
}
add_action('elementor/loaded', 'tfm_init_elementor_integration', 20);

// Ensure Elementor integration is available if Elementor loaded before our plugin
add_action('plugins_loaded', function () {
    if (did_action('elementor/loaded')) {
        tfm_init_elementor_integration();
    }
}, 25);

// Load plugin settings
if (!function_exists('tfm_load_settings')) {
    function tfm_load_settings() {
        // Per-request cache: the settings option and its ~55-key defaults array are
        // read on dozens of hooks/shortcodes/widgets per page. Build once, then
        // reuse. Invalidated by tfm_clear_settings_cache() when the option changes.
        global $tfm_settings_cache;
        if (is_array($tfm_settings_cache)) {
            return $tfm_settings_cache;
        }

        $defaults = [
            'enabled' => true,
            'elementor_enabled' => false,
            'divi_enabled' => true,
            'enable_logging' => true,
            'log_retention_days' => 30,
            'log_level' => 'all',
            'excluded_pages' => [],
            'excluded_roles' => [],
            'custom_selectors' => '',
            'debug_mode' => false,
            'enable_shortcodes' => true,
            'enable_svg_uploads' => false,
            'defer_scripts' => false,
            'custom_head_scripts' => '',
            'custom_footer_scripts' => '',
            'phone' => '',
            'phone_format' => '4', // Default to format 4 (xxx-xxx-xxxx) to maintain backward compatibility
            'email' => '',
            'enable_userway' => false,
            'userway_account_id' => '',
            'userway_color' => '#003D71',
            'userway_position' => 'bottom_right',
            'userway_mobile_position' => 'default',
            'enable_accessibe' => false,
            'accessibe_language' => 'en',
            'accessibe_position_x' => 'right',
            'accessibe_position_y' => 'bottom',
            'accessibe_color' => '#146FF8',
            'accessibe_statement_link' => '',
            'accessibe_hide_mobile' => false,
            'accessibe_trigger_icon' => 'people',
            'accessibe_trigger_size' => 'medium',
            'accessibe_trigger_shape' => 'round',
            'enable_whatconverts' => false,
            'whatconverts_account_id' => '',
            'defer_handles' => '',
            'login_logo_url' => '',
            'lead_magnet' => [
                'image_id' => 0,
                'file_id' => 0
            ],
            'enable_news' => false,
        'franchisee_financials' => [
            'estimated_initial_investment' => '',
            'minimum_liquid_capital' => '',
            'franchise_fee' => '',
            'net_worth' => '',
            'average_unit_volume' => ''
        ],
            'full_address' => '',
            'sitemap_enabled' => false, // HTML sitemap feature - disabled by default for backward compatibility
            'sitemap_post_types' => ['page', 'post'], // Default post types to include
            'sitemap_show_dates' => true,
            'sitemap_show_counts' => true,
            'sitemap_cache_timeout' => 3600, // 1 hour cache
            'sitemap_exclude_empty_cats' => true,
            'disable_wp_revisions' => false,
            'wp_post_revisions_limit' => 5,
            'disable_emojis' => false,
            'disable_jquery_migrate' => false,
            'disable_oembeds' => false
        ];
        
        $settings = get_option('tfm_plugin_settings', []);
        $tfm_settings_cache = wp_parse_args($settings, $defaults);
        return $tfm_settings_cache;
    }
}

// Invalidate the per-request settings cache whenever the option changes, so code
// that reads settings after a save within the same request sees fresh values.
if (!function_exists('tfm_clear_settings_cache')) {
    function tfm_clear_settings_cache() {
        global $tfm_settings_cache;
        $tfm_settings_cache = null;
    }
    add_action('add_option_tfm_plugin_settings', 'tfm_clear_settings_cache');
    add_action('update_option_tfm_plugin_settings', 'tfm_clear_settings_cache');
}

// Action logging function
function tfm_log_action($action, $data = []) {
    global $tfm_logger;
    $settings = tfm_load_settings();

    if (isset($settings['enable_logging']) && $settings['enable_logging'] && $tfm_logger) {
        $tfm_logger->log_action($action, $data);
    }
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

    update_option('tfm_plugin_db_version', TFM_PLUGIN_VERSION);
}
// Run on 'init' (fires on front-end, admin, and cron) so logging is enabled on
// the very first request after the update, not only when an admin visits wp-admin.
add_action('init', 'tfm_maybe_run_upgrades');

// Enqueue scripts conditionally
function tfm_enqueue_scripts() {
    $settings = tfm_load_settings();
    
    // Font Awesome and optional deferral
    wp_enqueue_script('font-awesome', 'https://kit.fontawesome.com/79c9dcfe2d.js', [], null, true);
    if (!has_filter('script_loader_tag', 'tfm_defer_scripts')) {
        add_filter('script_loader_tag', 'tfm_defer_scripts', 10, 2);
    }

    // Enqueue phone formatter script
    wp_enqueue_script(
        'tfm-phone-formatter',
        plugin_dir_url(__FILE__) . 'assets/js/phone-formatter.js',
        [],
        '1.0.0',
        true
    );

    // Add UserWay widget if enabled
    if ($settings['enable_userway'] && !empty($settings['userway_account_id'])) {
        // Map position values to UserWay's numeric values according to documentation
        $position_map = [
            'top_right' => '1',
            'middle_right' => '2',
            'bottom_right' => '3',
            'bottom_middle' => '4',
            'bottom_left' => '5',
            'middle_left' => '6',
            'top_left' => '7',
            'top_middle' => '8'
        ];
        
        $mobile_position_map = [
            'default' => '0',
            'top_right' => '1',
            'middle_right' => '2',
            'bottom_right' => '3',
            'bottom_middle' => '4',
            'bottom_left' => '5',
            'middle_left' => '6',
            'top_left' => '7',
            'top_middle' => '8'
        ];
        
        $position = isset($position_map[$settings['userway_position']]) ? $position_map[$settings['userway_position']] : '3'; // Default to bottom_right
        $mobile_position = isset($mobile_position_map[$settings['userway_mobile_position']]) ? $mobile_position_map[$settings['userway_mobile_position']] : '0';
        
        // Register the script with data attributes
        wp_register_script(
            'userway-widget',
            'https://cdn.userway.org/widget.js',
            [],
            null,
            true
        );
        
        // Add data attributes to the script tag
        add_filter('script_loader_tag', function($tag, $handle) use ($settings, $position, $mobile_position) {
            if ($handle === 'userway-widget') {
                $tag = str_replace(
                    '></script>',
                    sprintf(
                        ' data-account="%s" data-position="%s" data-mobile-position="%s" data-color="%s"></script>',
                        esc_attr($settings['userway_account_id']),
                        esc_attr($position),
                        esc_attr($mobile_position),
                        esc_attr($settings['userway_color'])
                    ),
                    $tag
                );
            }
            return $tag;
        }, 10, 2);
        
        wp_enqueue_script('userway-widget');
    }

    // Add WhatConverts tracking if enabled
    if ($settings['enable_whatconverts'] && !empty($settings['whatconverts_account_id'])) {
        // Add the initialization code
        wp_add_inline_script('jquery', 'var $wc_load=function(a){return JSON.parse(JSON.stringify(a))},$wc_leads=$wc_leads||{doc:{url:$wc_load(document.URL),ref:$wc_load(document.referrer),search:$wc_load(location.search),hash:$wc_load(location.hash)}};', 'before');
        
        // Add the main tracking script
        $account_id = esc_js($settings['whatconverts_account_id']);
        wp_enqueue_script('whatconverts', "//s.ksrndkehqnwntyxlhgto.com/{$account_id}.js", ['jquery'], null, true);
    }

    // Enqueue video defer styles
    wp_enqueue_style(
        'tfm-video-defer',
        TFM_PLUGIN_URL . 'assets/css/video-defer.css',
        [],
        TFM_PLUGIN_VERSION
    );
}
add_action('wp_enqueue_scripts', 'tfm_enqueue_scripts');

// Function to defer scripts
function tfm_defer_scripts($tag, $handle) {
    $settings = tfm_load_settings();
    if (empty($settings['defer_scripts'])) return $tag;

    $allowlist = array_filter(array_map('trim', explode(',', $settings['defer_handles'] ?? '')));
    if (!empty($allowlist) && in_array($handle, $allowlist, true)) {
        // Defer only external scripts with src
        if (strpos($tag, ' src=') !== false) {
            return str_replace(' src', ' defer src', $tag);
        }
    }
    return $tag;
}

// Allow SVG uploads if enabled — only for users who can already post unfiltered
// HTML (admins / super admins). This prevents lower-privilege users from
// uploading a scripted SVG that would execute in an admin's browser (stored XSS).
function tfm_allow_svg_uploads($mimes) {
    $settings = tfm_load_settings();
    if (!empty($settings['enable_svg_uploads']) && current_user_can('unfiltered_html')) {
        // Only plain .svg — .svgz (gzipped) can't be DOM-sanitized without
        // gunzipping, so it's not accepted rather than shipped as a dead path.
        $mimes['svg'] = 'image/svg+xml';
    }
    return $mimes;
}
add_filter('upload_mimes', 'tfm_allow_svg_uploads');

// WordPress's real-mime check (finfo) reports SVGs as text/plain or image/svg,
// which fails the upload. When SVG uploads are permitted for this user, accept
// the .svg extension so legitimate files can be stored.
function tfm_fix_svg_filetype($data, $file, $filename, $mimes, $real_mime = '') {
    $settings = tfm_load_settings();
    if (empty($settings['enable_svg_uploads']) || !current_user_can('unfiltered_html')) {
        return $data;
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'svg') {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'tfm_fix_svg_filetype', 10, 5);

// Sanitize every SVG before it is stored — strip scripts, event handlers,
// external entities, and script URIs. Reject the upload if it can't be made safe.
function tfm_sanitize_svg_on_upload($upload) {
    if (!isset($upload['type'], $upload['file']) || $upload['type'] !== 'image/svg+xml') {
        return $upload;
    }

    $contents = file_get_contents($upload['file']);
    // Fail closed: if we can't read it, we can't prove it's safe.
    if ($contents === false) {
        @unlink($upload['file']);
        return ['error' => __('This SVG could not be read for sanitization and was not uploaded.', 'topfiremedia')];
    }

    $clean = TFM_SVG_Sanitizer::sanitize($contents);
    if ($clean === false) {
        @unlink($upload['file']);
        return ['error' => __('This SVG could not be processed safely and was not uploaded.', 'topfiremedia')];
    }

    if (file_put_contents($upload['file'], $clean) === false) {
        @unlink($upload['file']);
        return ['error' => __('This SVG could not be saved safely and was not uploaded.', 'topfiremedia')];
    }
    return $upload;
}
add_filter('wp_handle_upload', 'tfm_sanitize_svg_on_upload');
add_filter('wp_handle_upload_prefilter', function ($file) {
    // Prefilter runs before the type is finalized; sanitize by extension here too.
    if (empty($file['name']) || !preg_match('/\.svg$/i', $file['name']) || empty($file['tmp_name'])) {
        return $file;
    }

    $settings = tfm_load_settings();
    if (empty($settings['enable_svg_uploads']) || !current_user_can('unfiltered_html')) {
        $file['error'] = __('SVG uploads are not permitted for your account.', 'topfiremedia');
        return $file;
    }

    $contents = file_get_contents($file['tmp_name']);
    if ($contents === false) {
        $file['error'] = __('This SVG could not be read for sanitization and was not uploaded.', 'topfiremedia');
        return $file;
    }

    $clean = TFM_SVG_Sanitizer::sanitize($contents);
    if ($clean === false) {
        $file['error'] = __('This SVG could not be processed safely and was not uploaded.', 'topfiremedia');
        return $file;
    }

    if (file_put_contents($file['tmp_name'], $clean) === false) {
        $file['error'] = __('This SVG could not be saved safely and was not uploaded.', 'topfiremedia');
    }
    return $file;
});

// Shortcodes
if (tfm_load_settings()['enable_shortcodes']) {
    // Basic shortcodes
    function tfm_year_shortcode() { return esc_html(date_i18n('Y')); }
    function tfm_site_title_shortcode() { return esc_html(get_bloginfo('name')); }
    function tfm_page_title_shortcode() { return esc_html(get_the_title()); }

    add_shortcode('year', 'tfm_year_shortcode');
    add_shortcode('site_title', 'tfm_site_title_shortcode');
    add_shortcode('page_title', 'tfm_page_title_shortcode');
    // Phone shortcode
    function tfm_phone_shortcode() {
        $settings = tfm_load_settings();
        $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? ''); // Remove all non-numeric characters

        // Ensure it's exactly 10 digits
        if (strlen($raw_phone) !== 10) {
            return esc_html('000-000-0000'); // Default if invalid
        }

        // Get the selected format (default to format 4 for backward compatibility)
        $format = isset($settings['phone_format']) ? $settings['phone_format'] : '4';

        // Format based on selected option
        switch ($format) {
            case '1': // +1 (xxx) xxx-xxxx
                $formatted_phone = '+1 (' . substr($raw_phone, 0, 3) . ') ' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '2': // +1-xxx-xxx-xxxx
                $formatted_phone = '+1-' . substr($raw_phone, 0, 3) . '-' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '3': // (xxx) xxx-xxxx
                $formatted_phone = '(' . substr($raw_phone, 0, 3) . ') ' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '4': // xxx-xxx-xxxx
            default:
                $formatted_phone = substr($raw_phone, 0, 3) . '-' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
        }

        return esc_html($formatted_phone);
    }
    add_shortcode('phone', 'tfm_phone_shortcode');

    // Phone text link shortcode - formatted display with tel: link
    function tfm_phone_text_link_shortcode() {
        $settings = tfm_load_settings();
        $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? ''); // Remove all non-numeric characters

        // Ensure it's exactly 10 digits
        if (strlen($raw_phone) !== 10) {
            return esc_html('000-000-0000'); // Default if invalid
        }

        // Get the selected format (default to format 4 for backward compatibility)
        $format = isset($settings['phone_format']) ? $settings['phone_format'] : '4';

        // Format based on selected option
        switch ($format) {
            case '1': // +1 (xxx) xxx-xxxx
                $formatted_phone = '+1 (' . substr($raw_phone, 0, 3) . ') ' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '2': // +1-xxx-xxx-xxxx
                $formatted_phone = '+1-' . substr($raw_phone, 0, 3) . '-' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '3': // (xxx) xxx-xxxx
                $formatted_phone = '(' . substr($raw_phone, 0, 3) . ') ' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '4': // xxx-xxx-xxxx
            default:
                $formatted_phone = substr($raw_phone, 0, 3) . '-' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
        }

        // Create tel: link with +1 prefix
        $tel_link = 'tel:+1' . $raw_phone;

        return '<a href="' . esc_attr($tel_link) . '">' . esc_html($formatted_phone) . '</a>';
    }
    add_shortcode('phone_text_link', 'tfm_phone_text_link_shortcode');

    // Phone link shortcode for Elementor compatibility
    function tfm_phone_link_shortcode() {
        $settings = tfm_load_settings();
        $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? ''); // Remove all non-numeric characters

        // Ensure it's exactly 10 digits and add +1 prefix for tel: links
        if (strlen($raw_phone) === 10) {
            $tel_phone = 'tel:+1' . $raw_phone; // Add tel: prefix and +1 for US numbers
            return esc_attr($tel_phone);
        } else {
            return 'tel:+10000000000'; // Default if invalid
        }
    }
    add_shortcode('phone_link', 'tfm_phone_link_shortcode');

    // Phone number shortcode for Elementor button links (complete tel: link)
    function tfm_phone_number_shortcode() {
        $settings = tfm_load_settings();
        $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? ''); // Remove all non-numeric characters

        // Ensure it's exactly 10 digits and add +1 prefix with tel: protocol
        if (strlen($raw_phone) === 10) {
            $tel_phone = 'tel:+1' . $raw_phone; // Complete tel: link
            return esc_attr($tel_phone);
        } else {
            return 'tel:+10000000000'; // Default if invalid
        }
    }
    add_shortcode('phone_number', 'tfm_phone_number_shortcode');

    // HTML Sitemap shortcode
    function tfm_sitemap_shortcode($atts) {
        $atts = shortcode_atts([
            'post_types' => '',
            'show_dates' => '',
            'show_counts' => '',
            'exclude_empty_cats' => ''
        ], $atts);

        // Convert string attributes to appropriate types
        $args = [];
        if (!empty($atts['post_types'])) {
            $args['post_types'] = $atts['post_types'];
        }
        if ($atts['show_dates'] !== '') {
            $args['show_dates'] = $atts['show_dates'];
        }
        if ($atts['show_counts'] !== '') {
            $args['show_counts'] = $atts['show_counts'];
        }
        if ($atts['exclude_empty_cats'] !== '') {
            $args['exclude_empty_cats'] = $atts['exclude_empty_cats'];
        }

        // Prevent wpautop from being applied to this shortcode output
        $output = tfm_sitemap_generate($args);
        return $output;
    }
    add_shortcode('tfm_sitemap', 'tfm_sitemap_shortcode');

// Create a global variable that Elementor can access
add_action('wp_head', function() {
    $settings = tfm_load_settings();
    $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? '');
    $phone_number = '';
    
    if (strlen($raw_phone) === 10) {
        $phone_number = '+1' . $raw_phone;
    } else {
        $phone_number = '+10000000000';
    }
    
    echo '<script>window.tfmPhoneNumber = "' . esc_js($phone_number) . '";</script>';
});


    // Franchisee Financial Shortcodes
    function tfm_estimated_initial_investment_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['estimated_initial_investment']) ? 
                  esc_html($settings['franchisee_financials']['estimated_initial_investment']) : 'Not specified';
        return $amount;
    }
    add_shortcode('estimated_initial_investment', 'tfm_estimated_initial_investment_shortcode');

    function tfm_minimum_liquid_capital_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['minimum_liquid_capital']) ? 
                  esc_html($settings['franchisee_financials']['minimum_liquid_capital']) : 'Not specified';
        return $amount;
    }
    add_shortcode('minimum_liquid_capital', 'tfm_minimum_liquid_capital_shortcode');

    function tfm_franchise_fee_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['franchise_fee']) ? 
                  esc_html($settings['franchisee_financials']['franchise_fee']) : 'Not specified';
        return $amount;
    }
    add_shortcode('franchise_fee', 'tfm_franchise_fee_shortcode');

    function tfm_net_worth_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['net_worth']) ? 
                  esc_html($settings['franchisee_financials']['net_worth']) : 'Not specified';
        return $amount;
    }
    add_shortcode('net_worth', 'tfm_net_worth_shortcode');

    function tfm_average_unit_volume_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['average_unit_volume']) ? 
                  esc_html($settings['franchisee_financials']['average_unit_volume']) : 'Not specified';
        return $amount;
    }
    add_shortcode('average_unit_volume', 'tfm_average_unit_volume_shortcode');

    // Debug shortcode to test if financial shortcodes are working
    function tfm_financial_test_shortcode() {
        $settings = tfm_load_settings();
        $financials = $settings['franchisee_financials'] ?? [];
        return 'Financial Test: ' . print_r($financials, true);
    }
    add_shortcode('financial_test', 'tfm_financial_test_shortcode');

    // Full Address Shortcode
    function tfm_full_address_shortcode() {
        $settings = tfm_load_settings();
        if (empty($settings['full_address'])) {
            return '';
        }
        
        $address = $settings['full_address'];
        // Convert line breaks to <br> tags for proper display
        $address = wp_kses_post($address);
        $address = nl2br($address);
        
        return $address;
    }
    add_shortcode('full_address', 'tfm_full_address_shortcode');

    // Email shortcode
    function tfm_email_shortcode() {
        $settings = tfm_load_settings();
        $email = !empty($settings['email']) ? sanitize_email($settings['email']) : 'info@example.com';

        return '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
    }
    add_shortcode('email', 'tfm_email_shortcode');

    // Lead Magnet shortcodes
    function tfm_lead_magnet_image_shortcode($atts = []) {
        $settings = tfm_load_settings();
        $image_id = absint($settings['lead_magnet']['image_id'] ?? 0);
        if (!$image_id) return '';
        $atts = shortcode_atts([
            'size'  => 'large',
            'class' => 'tfm-lead-magnet-image',
            'alt'   => 'Industry Outlook',
        ], $atts, 'lead_magnet_image');
        $img = wp_get_attachment_image($image_id, $atts['size'], false, ['class' => $atts['class'], 'alt' => $atts['alt']]);
        return $img ?: '';
    }
    add_shortcode('lead_magnet_image', 'tfm_lead_magnet_image_shortcode');

    function tfm_lead_magnet_link_shortcode($atts = []) {
        $settings = tfm_load_settings();
        $file_id = absint($settings['lead_magnet']['file_id'] ?? 0);
        if (!$file_id) return '';
        $atts = shortcode_atts([
            'text' => 'Download Industry Outlook',
            'class' => 'tfm-lead-magnet-link',
            'target' => '_blank',
            'rel' => 'noopener',
        ], $atts, 'lead_magnet_link');
        $url = wp_get_attachment_url($file_id);
        if (!$url) return '';
        $link = sprintf('<a href="%s" class="%s" target="%s" rel="%s">%s</a>', esc_url($url), esc_attr($atts['class']), esc_attr($atts['target']), esc_attr($atts['rel']), esc_html($atts['text']));
        return $link;
    }
    add_shortcode('lead_magnet_link', 'tfm_lead_magnet_link_shortcode');

    // Lead Magnet URL shortcode for Elementor compatibility
    function tfm_lead_magnet_url_shortcode() {
        $settings = tfm_load_settings();
        $file_id = absint($settings['lead_magnet']['file_id'] ?? 0);
        if (!$file_id) return '';
        $url = wp_get_attachment_url($file_id);
        return $url ? esc_url($url) : '';
    }
    add_shortcode('lead_magnet_url', 'tfm_lead_magnet_url_shortcode');
}

// Insert custom scripts from settings
function tfm_insert_custom_scripts() {
    $settings = tfm_load_settings();
    
    // Add telephone meta tag
    echo '<meta name="format-detection" content="telephone=no">';
    
    // Add custom head scripts
    if (!empty($settings['custom_head_scripts'])) {
        // Handle scripts without tags
        $script_content = trim($settings['custom_head_scripts']);
        if (strpos($script_content, '<script') === false) {
            echo "<script>\n" . $script_content . "\n</script>\n";
        } else {
            echo $script_content . "\n";
        }
    }
}
add_action('wp_head', 'tfm_insert_custom_scripts', 999);

function tfm_insert_footer_scripts() {
    $settings = tfm_load_settings();
    
    // Add custom footer scripts
    if (!empty($settings['custom_footer_scripts'])) {
        // Handle scripts without tags
        $script_content = trim($settings['custom_footer_scripts']);
        if (strpos($script_content, '<script') === false) {
            echo "<script>\n" . $script_content . "\n</script>\n";
        } else {
            echo $script_content . "\n";
        }
    }
}
add_action('wp_footer', 'tfm_insert_footer_scripts', 999);

// Output accessiBe accessWidget script
function tfm_output_accessibe_script() {
    $settings = tfm_load_settings();

    if (empty($settings['enable_accessibe'])) {
        return;
    }

    $position_x    = in_array($settings['accessibe_position_x'] ?? 'right', ['left', 'right']) ? $settings['accessibe_position_x'] : 'right';
    $position_y    = in_array($settings['accessibe_position_y'] ?? 'bottom', ['top', 'center', 'bottom']) ? $settings['accessibe_position_y'] : 'bottom';
    $color         = !empty($settings['accessibe_color']) ? $settings['accessibe_color'] : '#146FF8';
    $language      = sanitize_key($settings['accessibe_language'] ?? 'en');
    $statement     = esc_url($settings['accessibe_statement_link'] ?? '');
    $hide_mobile   = !empty($settings['accessibe_hide_mobile']);
    $valid_icons   = ['checkmark','display','display2','display3','help','people','people2','settings','settings2','wheels','wheels2'];
    $trigger_icon  = in_array($settings['accessibe_trigger_icon'] ?? 'people', $valid_icons) ? $settings['accessibe_trigger_icon'] : 'people';
    $valid_sizes   = ['small', 'medium', 'big'];
    $trigger_size  = in_array($settings['accessibe_trigger_size'] ?? 'medium', $valid_sizes) ? $settings['accessibe_trigger_size'] : 'medium';
    $trigger_shape = ($settings['accessibe_trigger_shape'] ?? 'round') === 'square' ? '0' : '50%';

    $config = [
        'statementLink'    => $statement,
        'footerHtml'       => '',
        'hideMobile'       => $hide_mobile,
        'hideTrigger'      => false,
        'disableBgProcess' => false,
        'language'         => $language,
        'position'         => $position_x,
        'leadColor'        => $color,
        'triggerColor'     => $color,
        'triggerRadius'    => $trigger_shape,
        'triggerPositionX' => $position_x,
        'triggerPositionY' => $position_y,
        'triggerIcon'      => $trigger_icon,
        'triggerSize'      => $trigger_size,
        'triggerOffsetX'   => 20,
        'triggerOffsetY'   => 20,
        'mobile'           => [
            'triggerSize'      => 'small',
            'triggerPositionX' => $position_x,
            'triggerPositionY' => $position_y,
            'triggerOffsetX'   => 20,
            'triggerOffsetY'   => 20,
            'triggerRadius'    => $trigger_shape,
        ],
    ];

    $config_json = wp_json_encode($config);
    echo "<script>\n(function(){\n  var s = document.createElement('script');\n  var h = document.querySelector('head') || document.body;\n  s.src = 'https://acsbapp.com/apps/app/dist/js/app.js';\n  s.async = true;\n  s.onload = function(){ acsbJS.init(" . $config_json . "); };\n  h.appendChild(s);\n})();\n</script>\n";
}
add_action('wp_footer', 'tfm_output_accessibe_script', 5);

// Add settings page
function tfm_add_settings_page() {
    add_menu_page(
        'TFM Custom Functions',
        'TFM Custom Functions',
        'manage_options',
        'tfm-custom-functions',
        'tfm_render_settings_page',
        'dashicons-admin-generic'
    );
    
    add_submenu_page(
        'tfm-custom-functions',
        'News Settings',
        'News Settings',
        'manage_options',
        'tfm-news-settings',
        'tfm_render_news_settings_page'
    );

    if (tfm_news_is_enabled()) {
        add_submenu_page(
            'tfm-custom-functions',
            'News Items',
            'News Items',
            'manage_options',
            'edit.php?post_type=tfm_news'
        );

        add_submenu_page(
            'tfm-custom-functions',
            'Add News Item',
            'Add News Item',
            'manage_options',
            'post-new.php?post_type=tfm_news'
        );
    }

    add_submenu_page(
        'tfm-custom-functions',
        'Activity Logs',
        'Activity Logs',
        'manage_options',
        'tfm-activity-logs',
        'tfm_render_logs_page'
    );

    // Debug page for sitemap testing
    add_submenu_page(
        'tfm-custom-functions',
        'Sitemap Debug',
        'Sitemap Debug',
        'manage_options',
        'tfm-sitemap-debug',
        'tfm_render_sitemap_debug_page'
    );
}


add_action('admin_menu', 'tfm_add_settings_page');

function tfm_news_is_enabled() {
    $settings = tfm_load_settings();
    return !empty($settings['enable_news']);
}

function tfm_register_news_post_type() {
    if (!tfm_news_is_enabled()) {
        return;
    }

    register_post_type('tfm_news', [
        'labels' => [
            'name' => __('News', 'topfiremedia'),
            'singular_name' => __('News Item', 'topfiremedia'),
            'add_new_item' => __('Add New News Item', 'topfiremedia'),
            'edit_item' => __('Edit News Item', 'topfiremedia'),
            'new_item' => __('New News Item', 'topfiremedia'),
            'view_item' => __('View News Item', 'topfiremedia'),
            'search_items' => __('Search News Items', 'topfiremedia'),
            'not_found' => __('No news items found', 'topfiremedia'),
            'not_found_in_trash' => __('No news items found in Trash', 'topfiremedia'),
            'menu_name' => __('News', 'topfiremedia'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_rest' => true,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'rewrite' => false,
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'author'],
        'menu_icon' => 'dashicons-media-document',
    ]);
}
add_action('init', 'tfm_register_news_post_type');

function tfm_news_add_metabox() {
    if (!tfm_news_is_enabled()) {
        return;
    }

    add_meta_box(
        'tfm_news_link_metabox',
        __('Outbound Article Settings', 'topfiremedia'),
        'tfm_news_metabox_callback',
        'tfm_news',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'tfm_news_add_metabox');

function tfm_news_metabox_callback($post) {
    wp_nonce_field('tfm_news_metabox_save', 'tfm_news_metabox_nonce');

    $outbound_url = get_post_meta($post->ID, '_tfm_news_outbound_url', true);
    $source_name = get_post_meta($post->ID, '_tfm_news_source_name', true);
    ?>
    <p>
        <label for="tfm_news_outbound_url"><strong><?php esc_html_e('Outbound URL', 'topfiremedia'); ?></strong></label><br>
        <input type="url" id="tfm_news_outbound_url" name="tfm_news_outbound_url" class="widefat" placeholder="https://example.com/original-article" value="<?php echo esc_attr($outbound_url); ?>">
        <span class="description"><?php esc_html_e('This is where users will be sent when they click this news card (opens in a new tab).', 'topfiremedia'); ?></span>
    </p>
    <p>
        <label for="tfm_news_source_name"><strong><?php esc_html_e('Source Name', 'topfiremedia'); ?></strong></label><br>
        <input type="text" id="tfm_news_source_name" name="tfm_news_source_name" class="widefat" placeholder="Forbes" value="<?php echo esc_attr($source_name); ?>">
        <span class="description"><?php esc_html_e('Optional source label shown in the widget (e.g., Forbes, CNN, Reuters).', 'topfiremedia'); ?></span>
    </p>
    <?php
}

function tfm_news_save_metabox($post_id) {
    if (!isset($_POST['tfm_news_metabox_nonce']) || !wp_verify_nonce($_POST['tfm_news_metabox_nonce'], 'tfm_news_metabox_save')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $post_type = get_post_type($post_id);
    if ($post_type !== 'tfm_news') {
        return;
    }

    $outbound_url = isset($_POST['tfm_news_outbound_url']) ? esc_url_raw(wp_unslash($_POST['tfm_news_outbound_url'])) : '';
    $source_name = isset($_POST['tfm_news_source_name']) ? sanitize_text_field(wp_unslash($_POST['tfm_news_source_name'])) : '';

    if (!empty($outbound_url)) {
        update_post_meta($post_id, '_tfm_news_outbound_url', $outbound_url);
    } else {
        delete_post_meta($post_id, '_tfm_news_outbound_url');
    }

    if (!empty($source_name)) {
        update_post_meta($post_id, '_tfm_news_source_name', $source_name);
    } else {
        delete_post_meta($post_id, '_tfm_news_source_name');
    }
}
add_action('save_post_tfm_news', 'tfm_news_save_metabox');

// Revision cleanup AJAX handlers
function tfm_get_revision_stats() {
    check_ajax_referer('tfm_revision_cleanup', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    global $wpdb;

    // Get total revision count
    $total_revisions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");

    // Get posts with revisions
    $posts_with_revisions = $wpdb->get_var("
        SELECT COUNT(DISTINCT p.post_parent)
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'revision'
        AND p.post_parent > 0
    ");

    // Estimate database size (rough calculation: ~1KB per revision)
    $estimated_size = round($total_revisions * 0.001, 2);

    wp_send_json_success([
        'total_revisions' => (int) $total_revisions,
        'posts_with_revisions' => (int) $posts_with_revisions,
        'estimated_size' => $estimated_size
    ]);
}

function tfm_cleanup_revisions() {
    check_ajax_referer('tfm_revision_cleanup', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    $cleanup_type = sanitize_text_field($_POST['cleanup_type']);
    $days = isset($_POST['days']) ? (int) $_POST['days'] : 30;

    global $wpdb;
    $deleted_count = 0;

    switch ($cleanup_type) {
        case 'excess':
            // Get the revision limit from settings
            $settings = get_option('tfm_plugin_settings', []);
            $limit = isset($settings['wp_post_revisions_limit']) ? (int) $settings['wp_post_revisions_limit'] : 5;

            if ($limit > 0) {
                // For each post, keep only the most recent N revisions
                $posts_with_revisions = $wpdb->get_results("
                    SELECT post_parent, COUNT(*) as revision_count
                    FROM {$wpdb->posts}
                    WHERE post_type = 'revision' AND post_parent > 0
                    GROUP BY post_parent
                    HAVING revision_count > {$limit}
                ");

                foreach ($posts_with_revisions as $post) {
                    // Delete excess revisions, keeping only the most recent ones
                    $deleted = $wpdb->query($wpdb->prepare("
                        DELETE r1 FROM {$wpdb->posts} r1
                        INNER JOIN {$wpdb->posts} r2 ON r1.post_parent = r2.post_parent
                        AND r1.post_type = 'revision' AND r2.post_type = 'revision'
                        AND r1.post_parent = %d
                        WHERE r1.post_date < r2.post_date
                        ORDER BY r1.post_date DESC
                        LIMIT %d
                    ", $post->post_parent, $post->revision_count - $limit));

                    $deleted_count += $deleted;
                }
            }
            break;

        case 'old':
            // Delete revisions older than X days
            $deleted_count = $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->posts}
                WHERE post_type = 'revision'
                AND post_date < DATE_SUB(NOW(), INTERVAL %d DAY)
            ", $days));
            break;

        case 'all':
            // Delete ALL revisions (destructive!)
            $deleted_count = $wpdb->delete($wpdb->posts, ['post_type' => 'revision']);
            break;
    }

    wp_send_json_success([
        'deleted_count' => (int) $deleted_count,
        'message' => "Successfully deleted {$deleted_count} revisions"
    ]);
}

add_action('wp_ajax_tfm_get_revision_stats', 'tfm_get_revision_stats');
add_action('wp_ajax_tfm_cleanup_revisions', 'tfm_cleanup_revisions');

// HTML Sitemap Metabox
function tfm_sitemap_add_metabox() {
    $settings = tfm_load_settings();

    // Only add metabox if sitemap is enabled
    if (!$settings['sitemap_enabled']) {
        return;
    }

    // Get all public post types
    $post_types = get_post_types(['public' => true], 'names');

    foreach ($post_types as $post_type) {
        // Skip attachments
        if ($post_type === 'attachment') {
            continue;
        }

        add_meta_box(
            'tfm_sitemap_metabox',
            'HTML Sitemap Settings',
            'tfm_sitemap_metabox_callback',
            $post_type,
            'side',
            'default'
        );
    }
}

add_action('add_meta_boxes', 'tfm_sitemap_add_metabox');

function tfm_sitemap_save_metabox($post_id) {
    // Check if sitemap is enabled first
    $settings = tfm_load_settings();
    if (!$settings['sitemap_enabled']) {
        return;
    }

    // Check nonce
    if (!isset($_POST['tfm_sitemap_metabox_nonce']) || !wp_verify_nonce($_POST['tfm_sitemap_metabox_nonce'], 'tfm_sitemap_metabox')) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save or delete the meta
    if (isset($_POST['tfm_sitemap_exclude']) && $_POST['tfm_sitemap_exclude'] == '1') {
        update_post_meta($post_id, '_tfm_sitemap_exclude', '1');
    } else {
        delete_post_meta($post_id, '_tfm_sitemap_exclude');
    }
}

add_action('save_post', 'tfm_sitemap_save_metabox');

function tfm_sitemap_metabox_callback($post) {
    wp_nonce_field('tfm_sitemap_metabox', 'tfm_sitemap_metabox_nonce');

    $exclude_from_sitemap = get_post_meta($post->ID, '_tfm_sitemap_exclude', true);
    ?>
    <p>
        <label for="tfm_sitemap_exclude">
            <input type="checkbox" id="tfm_sitemap_exclude" name="tfm_sitemap_exclude" value="1" <?php checked($exclude_from_sitemap, '1'); ?>>
            Exclude from HTML Sitemap
        </label>
    </p>
    <p class="description">
        When checked, this <?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name); ?> will not appear in HTML sitemaps generated by the TFM plugin.
    </p>
    <?php
}


function tfm_sitemap_get_posts($post_type, $args = []) {
    $settings = tfm_load_settings();

    // Default query args
    $query_args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => '_tfm_sitemap_exclude',
                'compare' => 'NOT EXISTS'
            ]
        ]
    ];

    // Allow shortcode args to override defaults
    if (isset($args['orderby'])) {
        $query_args['orderby'] = $args['orderby'];
    }
    if (isset($args['order'])) {
        $query_args['order'] = $args['order'];
    }

    $query = new WP_Query($query_args);
    return $query->posts;
}

function tfm_sitemap_get_pages_hierarchical($args = []) {
    $settings = tfm_load_settings();

    $query_args = [
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => '_tfm_sitemap_exclude',
                'compare' => 'NOT EXISTS'
            ]
        ]
    ];

    // Allow shortcode args to override defaults
    if (isset($args['orderby'])) {
        $query_args['orderby'] = $args['orderby'];
    }
    if (isset($args['order'])) {
        $query_args['order'] = $args['order'];
    }

    $query = new WP_Query($query_args);
    return $query->posts;
}

function tfm_sitemap_get_posts_by_category($args = []) {
    $settings = tfm_load_settings();

    $categories = get_categories([
        'hide_empty' => isset($settings['sitemap_exclude_empty_cats']) ? $settings['sitemap_exclude_empty_cats'] : true
    ]);

    $result = [];

    foreach ($categories as $category) {
        $query_args = [
            'cat' => $category->term_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_tfm_sitemap_exclude',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];

        // Allow shortcode args to override defaults
        if (isset($args['orderby'])) {
            $query_args['orderby'] = $args['orderby'];
        }
        if (isset($args['order'])) {
            $query_args['order'] = $args['order'];
        }

        $posts = get_posts($query_args);

        if (!empty($posts)) {
            $result[] = [
                'category' => $category,
                'posts' => $posts
            ];
        }
    }

    return $result;
}

function tfm_sitemap_format_date($date) {
    return mysql2date(get_option('date_format'), $date);
}

function tfm_sitemap_generate($args = []) {
    if (!tfm_sitemap_is_enabled()) {
        return '<p>HTML sitemap is not enabled.</p>';
    }

    $settings = tfm_load_settings();

    // Check cache first
    $cached = tfm_sitemap_get_cached($args);
    if ($cached !== false) {
        return $cached;
    }

    // Get post types to include
    $post_types = isset($args['post_types']) ? explode(',', $args['post_types']) : (isset($settings['sitemap_post_types']) ? $settings['sitemap_post_types'] : ['page', 'post']);

    // Get display options
    $show_dates = isset($args['show_dates']) ? filter_var($args['show_dates'], FILTER_VALIDATE_BOOLEAN) : (isset($settings['sitemap_show_dates']) ? $settings['sitemap_show_dates'] : true);
    $show_counts = isset($args['show_counts']) ? filter_var($args['show_counts'], FILTER_VALIDATE_BOOLEAN) : (isset($settings['sitemap_show_counts']) ? $settings['sitemap_show_counts'] : true);

    ob_start();
    ?>

    <div class="tfm-html-sitemap">
        <?php foreach ($post_types as $post_type): ?>
            <?php
            $post_type_obj = get_post_type_object($post_type);
            if (!$post_type_obj) continue;

            $section_title = $post_type_obj->labels->name;
            ?>

            <div class="tfm-sitemap-section tfm-sitemap-<?php echo esc_attr($post_type); ?>">
                <h2><?php echo esc_html($section_title); ?></h2>

                <?php if ($post_type === 'page'): ?>
                    <?php $pages = tfm_sitemap_get_pages_hierarchical($args);
                    if (!empty($pages)):
                        echo '<ul class="tfm-sitemap-pages">';
                        foreach ($pages as $page):
                            echo '<li><a href="' . esc_url(get_permalink($page->ID)) . '">' . esc_html($page->post_title) . '</a>';
                            if ($show_dates) {
                                echo ' <span class="tfm-sitemap-date">(' . esc_html(tfm_sitemap_format_date($page->post_modified)) . ')</span>';
                            }
                            echo '</li>';
                        endforeach;
                        echo '</ul>';
                    else:
                        echo '<p>No pages found.</p>';
                    endif; ?>

                <?php elseif ($post_type === 'post'): ?>
                    <?php
                    $posts_by_category = tfm_sitemap_get_posts_by_category($args);
                    if (!empty($posts_by_category)):
                    ?>
                        <?php foreach ($posts_by_category as $category_data): ?>
                            <div class="tfm-sitemap-category">
                                <h3><?php echo esc_html($category_data['category']->name); if ($show_counts): ?><span class="tfm-sitemap-count">(<?php echo count($category_data['posts']); ?>)</span><?php endif; ?></h3>
                                <ul><?php foreach ($category_data['posts'] as $post): ?>
                                    <li><a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a><?php if ($show_dates): ?> <span class="tfm-sitemap-date">(<?php echo esc_html(tfm_sitemap_format_date($post->post_modified)); ?>)</span><?php endif; ?></li>
                                <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No posts found.</p>
                    <?php endif; ?>

                <?php else: ?>
                    <?php
                    $posts = tfm_sitemap_get_posts($post_type, $args);
                    if (!empty($posts)):
                    ?>
                        <ul class="tfm-sitemap-posts"><?php foreach ($posts as $post): ?>
                            <li><a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a><?php if ($show_dates): ?> <span class="tfm-sitemap-date">(<?php echo esc_html(tfm_sitemap_format_date($post->post_modified)); ?>)</span><?php endif; ?></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No <?php echo esc_html($section_title); ?> found.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    $content = ob_get_clean();

    // Remove wpautop and other content filters that could mess with the HTML
    $content = preg_replace('/<p><\/p>/', '', $content); // Remove empty paragraphs
    $content = preg_replace('/<br\s*\/?>/', '', $content); // Remove stray br tags

    // Cache the result
    tfm_sitemap_set_cached($content, $args);

    return $content;
}

// Add settings page styles
function tfm_admin_styles() {
    $screen = get_current_screen();
    if ($screen->id !== 'toplevel_page_tfm-custom-functions') return;
    
    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    ?>
    <style>
        /* Main container */
        .tfm-settings-wrap {
            max-width: 1400px; /* Wider max-width */
            margin: 20px 0; /* Center the content */
        }

        /* Tab Navigation */
        .tfm-tabs {
            margin: 0 0 20px;
            padding: 0;
            border-bottom: 1px solid #ccd0d4;
        }
        .tfm-tabs li {
            display: inline-block;
            margin: 0;
            padding: 0;
        }
        .tfm-tabs a {
            display: block;
            padding: 10px 20px;
            text-decoration: none;
            color: #555;
            border: 1px solid #ccd0d4;
            border-bottom: none;
            margin-right: 5px;
            background: #f1f1f1;
            border-radius: 4px 4px 0 0;
            margin-bottom: -1px;
        }
        .tfm-tabs a.active {
            background: #fff;
            border-bottom: 1px solid #fff;
            color: #000;
            font-weight: 600;
        }
        .tfm-tabs a:hover {
            background: #f9f9f9;
        }

        /* Tab Content */
        .tfm-tab-content {
            display: none;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-top: none;
        }
        .tfm-tab-content.active {
            display: block;
        }

        /* Form Elements */
        .form-table {
            background: #fff;
            margin-top: 0;
        }
        .form-table th {
            padding: 20px 40px 20px 0;
            min-width: 200px;
            width: 200px;
        }
        .form-table td {
            padding: 20px 40px 20px 0;
        }
        .form-table td .description {
            margin-top: 8px;
            color: #666;
            font-style: italic;
        }
        .tfm-settings-section {
            margin-bottom: 30px;
        }
        .tfm-settings-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .tfm-settings-description {
            color: #666;
            font-style: italic;
            margin-bottom: 15px;
        }

        /* Color Picker */
        .tfm-color-picker {
            width: 80px;
            height: 30px;
            padding: 0;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        /* Code Examples */
        .tfm-code-example {
            background: #f5f5f5;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-family: monospace;
            margin: 5px 0;
        }
        .large-text.code {
            font-family: monospace;
        }

        /* Submit Button */
        .tfm-submit-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        /* Copy Shortcode Buttons */
        .tfm-copy-shortcode {
            margin-left: 8px;
            font-size: 11px;
            padding: 2px 8px;
            height: auto;
            line-height: 1.4;
        }
        .tfm-copy-shortcode.button-primary {
            background: #00a32a;
            border-color: #00a32a;
        }
        .tfm-code-example {
            background: #f5f5f5;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-family: monospace;
            margin: 8px 0;
            line-height: 1.6;
        }
        .tfm-code-example strong {
            display: block;
            margin-bottom: 8px;
            color: #333;
        }
        .tfm-code-example code {
            background: #fff;
            padding: 2px 6px;
            border: 1px solid #ddd;
            border-radius: 2px;
            margin-right: 8px;
        }
    </style>
    <?php
}
add_action('admin_head', 'tfm_admin_styles');

// Render settings page
function tfm_render_settings_page() {
    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $settings = tfm_load_settings();
    ?>
    <div class="wrap tfm-settings-wrap">
        <h1>TFM Custom Functions Settings</h1>
        
        <form method="post" action="options.php">
            <?php 
            settings_fields('tfm_plugin_settings_group');
            ?>
            
            <ul class="tfm-tabs">
                <li><a href="#general" class="active">General</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="#franchisee">Franchisee Info</a></li>
                <li><a href="#accessibility">Accessibility</a></li>
                <li><a href="#tracking">Tracking</a></li>
                <li><a href="#logging">Logging</a></li>
                <li><a href="#scripts">Scripts</a></li>
                <li><a href="#video_defer">Video Defer</a></li>
                <li><a href="#lead_magnet">Lead Magnet</a></li>
                <li><a href="#html_sitemap">HTML Sitemap</a></li>
                <li><a href="#debug_debloat">Debug & Debloat</a></li>
            </ul>

            <div id="general" class="tfm-tab-content active">
                <div class="tfm-settings-section">
                    <h2>General Settings</h2>
                    <p class="tfm-settings-description">Configure basic functionality of the plugin.</p>
                    <table class="form-table">
                        <tr>
                            <th>Enable SVG Uploads</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[enable_svg_uploads]" value="1" <?php checked($settings['enable_svg_uploads'], true); ?>>
                                    Allow SVG file uploads in media library
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>Enable Shortcodes</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[enable_shortcodes]" value="1" <?php checked($settings['enable_shortcodes'], true); ?>>
                                    Enable built-in shortcodes
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>Defer JavaScript</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[defer_scripts]" value="1" <?php checked($settings['defer_scripts'], true); ?>>
                                    Defer loading of JavaScript files
                                </label>
                                <p class="description">When enabled, JavaScript files will load after the page renders, improving initial load time. Some thirdâ€‘party scripts may require early execution; if a script breaks, remove it from the allowlist below.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Defer Allowlist (handles)</th>
                            <td>
                                <input type="text" name="tfm_plugin_settings[defer_handles]" value="<?php echo esc_attr($settings['defer_handles']); ?>" class="regular-text" placeholder="handle-one,handle-two">
                                <p class="description">Comma-separated WordPress script handles to defer when Defer JavaScript is enabled. Example: <code>font-awesome,userway-widget</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Login Logo</th>
                            <td>
                                <div style="margin-bottom:10px;">
                                    <img id="tfm-login-logo-preview" src="<?php echo $settings['login_logo_url'] ? esc_url($settings['login_logo_url']) : ''; ?>" style="max-width:200px; height:auto; <?php echo $settings['login_logo_url'] ? '' : 'display:none;'; ?>" alt="Login Logo Preview">
                                </div>
                                <input type="hidden" id="tfm-login-logo-url" name="tfm_plugin_settings[login_logo_url]" value="<?php echo esc_attr($settings['login_logo_url']); ?>">
                                <button type="button" class="button" id="tfm-login-logo-upload">Select Logo</button>
                                <button type="button" class="button" id="tfm-login-logo-remove" <?php echo $settings['login_logo_url'] ? '' : 'style="display:none;"'; ?>>Remove</button>
                                <p class="description">Optional. If set, replaces the WordPress login logo. Leave blank to use default.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div id="contact" class="tfm-tab-content">
                <div class="tfm-settings-section">
                    <h2>Contact Information</h2>
                    <p class="tfm-settings-description">Set up contact information for use with shortcodes. Each field has a corresponding shortcode for use in Elementor or other content areas.</p>
                    <table class="form-table">
                        <tr>
                            <th>Phone Number</th>
                            <td>
                                <input type="text" name="tfm_plugin_settings[phone]" value="<?php echo esc_attr($settings['phone'] ?? ''); ?>" placeholder="000-000-0000" class="regular-text">
                                <p class="description">Enter phone number (minimum 10 digits required). Format: xxx-xxx-xxxx</p>
                                <p class="description">
                                    Shortcodes: 
                                    <code>[phone]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone]">Copy</button> (formatted display)
                                    <code>[phone_text_link]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone_text_link]">Copy</button> (formatted display with tel: link)
                                    <code>[phone_link]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone_link]">Copy</button> (for tel: links)
                                    <code>[phone_number]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone_number]">Copy</button> (for Elementor Dynamic Tags)
                                    <br><strong>Elementor Usage:</strong> In Elementor button → Link → Dynamic Tag → Shortcode → Use <code>[phone_number]</code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>Phone Display Format</th>
                            <td>
                                <select name="tfm_plugin_settings[phone_format]" class="regular-text">
                                    <option value="1" <?php selected($settings['phone_format'] ?? '4', '1'); ?>>+1 (xxx) xxx-xxxx</option>
                                    <option value="2" <?php selected($settings['phone_format'] ?? '4', '2'); ?>>+1-xxx-xxx-xxxx</option>
                                    <option value="3" <?php selected($settings['phone_format'] ?? '4', '3'); ?>>(xxx) xxx-xxxx</option>
                                    <option value="4" <?php selected($settings['phone_format'] ?? '4', '4'); ?>>xxx-xxx-xxxx</option>
                                </select>
                                <p class="description">Choose how the phone number is displayed when using the <code>[phone]</code> shortcode.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>
                                <input type="email" name="tfm_plugin_settings[email]" value="<?php echo esc_attr($settings['email'] ?? ''); ?>" placeholder="info@example.com" class="regular-text">
                                <p class="description">Shortcode: <code>[email]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[email]">Copy</button> (creates mailto: link)</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Business Address</th>
                            <td>
                                <textarea name="tfm_plugin_settings[full_address]" rows="4" cols="50" class="large-text" placeholder="123 Main Street&#10;City, State 12345&#10;Country"><?php echo esc_textarea($settings['full_address'] ?? ''); ?></textarea>
                                <p class="description">Shortcode: <code>[full_address]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[full_address]">Copy</button></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="tfm-settings-section">
                    <h3>Contact Shortcodes Reference</h3>
                    <p class="tfm-settings-description">Complete list of contact-related shortcodes:</p>
                    <div class="tfm-code-example">
                        <strong>Contact Information:</strong><br>
                        <code>[phone]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone]">Copy</button> - Displays formatted phone number<br>
                        <code>[phone_text_link]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone_text_link]">Copy</button> - Displays formatted phone number with tel: link<br>
                        <code>[phone_link]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone_link]">Copy</button> - Outputs tel: link for Elementor<br>
                        <code>[phone_number]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone_number]">Copy</button> - Outputs tel: link for Elementor Dynamic Tags<br>
                        <code>[email]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[email]">Copy</button> - Creates clickable mailto link<br>
                        <code>[full_address]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[full_address]">Copy</button> - Displays complete business address
                    </div>
                </div>
            </div>

            <div id="franchisee" class="tfm-tab-content">
                <div class="tfm-settings-section">
                    <h2>Franchisee Financial Information</h2>
                    <p class="tfm-settings-description">Configure financial information for franchisee prospects. Each field has a corresponding shortcode for use in Elementor or other content areas.</p>
                    <table class="form-table">
                        <tr>
                            <th>Estimated Initial Investment</th>
                            <td>
                                <input type="text" name="tfm_plugin_settings[franchisee_financials][estimated_initial_investment]" value="<?php echo esc_attr($settings['franchisee_financials']['estimated_initial_investment'] ?? ''); ?>" placeholder="e.g., $50,000 - $100,000" class="regular-text">
                                <p class="description">Shortcode: <code>[estimated_initial_investment]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[estimated_initial_investment]">Copy</button></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Minimum Liquid Capital</th>
                            <td>
                                <input type="text" name="tfm_plugin_settings[franchisee_financials][minimum_liquid_capital]" value="<?php echo esc_attr($settings['franchisee_financials']['minimum_liquid_capital'] ?? ''); ?>" placeholder="e.g., $25,000" class="regular-text">
                                <p class="description">Shortcode: <code>[minimum_liquid_capital]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[minimum_liquid_capital]">Copy</button></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Franchise Fee</th>
                            <td>
                                <input type="text" name="tfm_plugin_settings[franchisee_financials][franchise_fee]" value="<?php echo esc_attr($settings['franchisee_financials']['franchise_fee'] ?? ''); ?>" placeholder="e.g., $25,000" class="regular-text">
                                <p class="description">Shortcode: <code>[franchise_fee]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[franchise_fee]">Copy</button></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Net Worth Requirement</th>
                            <td>
                                <input type="text" name="tfm_plugin_settings[franchisee_financials][net_worth]" value="<?php echo esc_attr($settings['franchisee_financials']['net_worth'] ?? ''); ?>" placeholder="e.g., $100,000" class="regular-text">
                                <p class="description">Shortcode: <code>[net_worth]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[net_worth]">Copy</button></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Average Unit Volume</th>
                            <td>
                                <input type="text" name="tfm_plugin_settings[franchisee_financials][average_unit_volume]" value="<?php echo esc_attr($settings['franchisee_financials']['average_unit_volume'] ?? ''); ?>" placeholder="e.g., $500,000" class="regular-text">
                                <p class="description">Shortcode: <code>[average_unit_volume]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[average_unit_volume]">Copy</button></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="tfm-settings-section">
                    <h3>All Available Shortcodes</h3>
                    <p class="tfm-settings-description">Complete list of available shortcodes for use in Elementor or other content areas:</p>
                    <div class="tfm-code-example">
                        <strong>Contact:</strong><br>
                        <code>[phone]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone]">Copy</button><br>
                        <code>[phone_text_link]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone_text_link]">Copy</button> (formatted with tel: link)<br>
                        <code>[phone_link]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone_link]">Copy</button> (for tel: links)<br>
                        <code>[phone_number]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[phone_number]">Copy</button> (for Elementor Dynamic Tags)<br>
                        <code>[email]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[email]">Copy</button><br>
                        <code>[full_address]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[full_address]">Copy</button>
                    </div>
                    <div class="tfm-code-example">
                        <strong>Franchisee Financials:</strong><br>
                        <code>[estimated_initial_investment]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[estimated_initial_investment]">Copy</button><br>
                        <code>[minimum_liquid_capital]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[minimum_liquid_capital]">Copy</button><br>
                        <code>[franchise_fee]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[franchise_fee]">Copy</button><br>
                        <code>[net_worth]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[net_worth]">Copy</button><br>
                        <code>[average_unit_volume]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[average_unit_volume]">Copy</button>
                    </div>
                    <div class="tfm-code-example">
                        <strong>General:</strong><br>
                        <code>[year]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[year]">Copy</button><br>
                        <code>[site_title]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[site_title]">Copy</button><br>
                        <code>[page_title]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[page_title]">Copy</button>
                    </div>
                    <div class="tfm-code-example">
                        <strong>Lead Magnet:</strong><br>
                        <code>[lead_magnet_image]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[lead_magnet_image]">Copy</button><br>
                        <code>[lead_magnet_link]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[lead_magnet_link]">Copy</button><br>
                        <code>[lead_magnet_url]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[lead_magnet_url]">Copy</button>
                    </div>
                </div>
            </div>

            <div id="accessibility" class="tfm-tab-content">
                <div class="tfm-settings-section">
                    <h2>Accessibility Settings</h2>
                    <p class="tfm-settings-description">Configure UserWay and accessiBe accessibility widget integrations.</p>

                    <h3 style="margin-top:24px;">UserWay</h3>
                    <table class="form-table">
                        <tr>
                            <th>Enable UserWay Widget</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[enable_userway]" value="1" <?php checked($settings['enable_userway'], true); ?>>
                                    Enable the UserWay accessibility widget
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>UserWay Account ID</th>
                            <td>
                                <input type="text" name="tfm_plugin_settings[userway_account_id]" value="<?php echo esc_attr($settings['userway_account_id'] ?? ''); ?>" class="regular-text" placeholder="Enter your UserWay account ID">
                                <p class="description">Enter your UserWay account ID from your UserWay dashboard.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Widget Color</th>
                            <td>
                                <input type="color" name="tfm_plugin_settings[userway_color]" value="<?php echo esc_attr($settings['userway_color']); ?>" class="tfm-color-picker">
                                <p class="description">Choose the color for the UserWay widget icon.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Widget Position</th>
                            <td>
                                <select name="tfm_plugin_settings[userway_position]" class="regular-text">
                                    <option value="top_right" <?php selected($settings['userway_position'], 'top_right'); ?>>Top Right</option>
                                    <option value="middle_right" <?php selected($settings['userway_position'], 'middle_right'); ?>>Middle Right</option>
                                    <option value="bottom_right" <?php selected($settings['userway_position'], 'bottom_right'); ?>>Bottom Right</option>
                                    <option value="bottom_middle" <?php selected($settings['userway_position'], 'bottom_middle'); ?>>Bottom Middle</option>
                                    <option value="bottom_left" <?php selected($settings['userway_position'], 'bottom_left'); ?>>Bottom Left</option>
                                    <option value="middle_left" <?php selected($settings['userway_position'], 'middle_left'); ?>>Middle Left</option>
                                    <option value="top_left" <?php selected($settings['userway_position'], 'top_left'); ?>>Top Left</option>
                                    <option value="top_middle" <?php selected($settings['userway_position'], 'top_middle'); ?>>Top Middle</option>
                                </select>
                                <p class="description">Choose where the widget appears on desktop screens.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Mobile Position</th>
                            <td>
                                <select name="tfm_plugin_settings[userway_mobile_position]" class="regular-text">
                                    <option value="default" <?php selected($settings['userway_mobile_position'], 'default'); ?>>Same as Desktop</option>
                                    <option value="top_right" <?php selected($settings['userway_mobile_position'], 'top_right'); ?>>Top Right</option>
                                    <option value="middle_right" <?php selected($settings['userway_mobile_position'], 'middle_right'); ?>>Middle Right</option>
                                    <option value="bottom_right" <?php selected($settings['userway_mobile_position'], 'bottom_right'); ?>>Bottom Right</option>
                                    <option value="bottom_middle" <?php selected($settings['userway_mobile_position'], 'bottom_middle'); ?>>Bottom Middle</option>
                                    <option value="bottom_left" <?php selected($settings['userway_mobile_position'], 'bottom_left'); ?>>Bottom Left</option>
                                    <option value="middle_left" <?php selected($settings['userway_mobile_position'], 'middle_left'); ?>>Middle Left</option>
                                    <option value="top_left" <?php selected($settings['userway_mobile_position'], 'top_left'); ?>>Top Left</option>
                                    <option value="top_middle" <?php selected($settings['userway_mobile_position'], 'top_middle'); ?>>Top Middle</option>
                                </select>
                                <p class="description">Choose where the widget appears on mobile devices (optional, defaults to desktop position).</p>
                            </td>
                        </tr>
                    </table>

                    <hr style="margin:32px 0;">

                    <h3>accessiBe (accessWidget)</h3>
                    <table class="form-table">
                        <tr>
                            <th>Enable accessWidget</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[enable_accessibe]" value="1" <?php checked($settings['enable_accessibe'] ?? false, true); ?>>
                                    Enable the accessiBe accessWidget
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>Widget Language</th>
                            <td>
                                <select name="tfm_plugin_settings[accessibe_language]" class="regular-text">
                                    <option value="en" <?php selected($settings['accessibe_language'] ?? 'en', 'en'); ?>>English</option>
                                    <option value="es" <?php selected($settings['accessibe_language'] ?? 'en', 'es'); ?>>Spanish</option>
                                    <option value="fr" <?php selected($settings['accessibe_language'] ?? 'en', 'fr'); ?>>French</option>
                                    <option value="de" <?php selected($settings['accessibe_language'] ?? 'en', 'de'); ?>>German</option>
                                    <option value="it" <?php selected($settings['accessibe_language'] ?? 'en', 'it'); ?>>Italian</option>
                                    <option value="pt" <?php selected($settings['accessibe_language'] ?? 'en', 'pt'); ?>>Portuguese</option>
                                    <option value="nl" <?php selected($settings['accessibe_language'] ?? 'en', 'nl'); ?>>Dutch</option>
                                    <option value="da" <?php selected($settings['accessibe_language'] ?? 'en', 'da'); ?>>Danish</option>
                                    <option value="sv" <?php selected($settings['accessibe_language'] ?? 'en', 'sv'); ?>>Swedish</option>
                                    <option value="no" <?php selected($settings['accessibe_language'] ?? 'en', 'no'); ?>>Norwegian</option>
                                    <option value="fi" <?php selected($settings['accessibe_language'] ?? 'en', 'fi'); ?>>Finnish</option>
                                    <option value="pl" <?php selected($settings['accessibe_language'] ?? 'en', 'pl'); ?>>Polish</option>
                                    <option value="ru" <?php selected($settings['accessibe_language'] ?? 'en', 'ru'); ?>>Russian</option>
                                    <option value="ar" <?php selected($settings['accessibe_language'] ?? 'en', 'ar'); ?>>Arabic</option>
                                    <option value="he" <?php selected($settings['accessibe_language'] ?? 'en', 'he'); ?>>Hebrew</option>
                                    <option value="ja" <?php selected($settings['accessibe_language'] ?? 'en', 'ja'); ?>>Japanese</option>
                                    <option value="zh" <?php selected($settings['accessibe_language'] ?? 'en', 'zh'); ?>>Chinese (Simplified)</option>
                                </select>
                                <p class="description">Language displayed in the accessWidget interface.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Horizontal Position</th>
                            <td>
                                <select name="tfm_plugin_settings[accessibe_position_x]" class="regular-text">
                                    <option value="right" <?php selected($settings['accessibe_position_x'] ?? 'right', 'right'); ?>>Right</option>
                                    <option value="left" <?php selected($settings['accessibe_position_x'] ?? 'right', 'left'); ?>>Left</option>
                                </select>
                                <p class="description">Horizontal side where the accessibility button appears.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Widget Color</th>
                            <td>
                                <input type="color" name="tfm_plugin_settings[accessibe_color]" value="<?php echo esc_attr($settings['accessibe_color'] ?? '#146FF8'); ?>" class="tfm-color-picker">
                                <p class="description">Color for the accessWidget button and interface highlights.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Accessibility Statement URL</th>
                            <td>
                                <input type="url" name="tfm_plugin_settings[accessibe_statement_link]" value="<?php echo esc_attr($settings['accessibe_statement_link'] ?? ''); ?>" class="regular-text" placeholder="https://yoursite.com/accessibility-statement">
                                <p class="description">Optional link to your site's accessibility statement (shown in the widget footer).</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Trigger Icon</th>
                            <td>
                                <select name="tfm_plugin_settings[accessibe_trigger_icon]" class="regular-text">
                                    <option value="people" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'people'); ?>>People (default)</option>
                                    <option value="people2" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'people2'); ?>>People 2</option>
                                    <option value="wheels" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'wheels'); ?>>Wheelchair</option>
                                    <option value="wheels2" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'wheels2'); ?>>Wheelchair 2</option>
                                    <option value="checkmark" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'checkmark'); ?>>Checkmark</option>
                                    <option value="display" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'display'); ?>>Display</option>
                                    <option value="display2" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'display2'); ?>>Display 2</option>
                                    <option value="display3" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'display3'); ?>>Display 3</option>
                                    <option value="help" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'help'); ?>>Help</option>
                                    <option value="settings" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'settings'); ?>>Settings</option>
                                    <option value="settings2" <?php selected($settings['accessibe_trigger_icon'] ?? 'people', 'settings2'); ?>>Settings 2</option>
                                </select>
                                <p class="description">Icon displayed on the floating accessibility button.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Trigger Size</th>
                            <td>
                                <select name="tfm_plugin_settings[accessibe_trigger_size]" class="regular-text">
                                    <option value="small" <?php selected($settings['accessibe_trigger_size'] ?? 'medium', 'small'); ?>>Small</option>
                                    <option value="medium" <?php selected($settings['accessibe_trigger_size'] ?? 'medium', 'medium'); ?>>Medium (default)</option>
                                    <option value="big" <?php selected($settings['accessibe_trigger_size'] ?? 'medium', 'big'); ?>>Big</option>
                                </select>
                                <p class="description">Size of the floating accessibility button.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Trigger Shape</th>
                            <td>
                                <select name="tfm_plugin_settings[accessibe_trigger_shape]" class="regular-text">
                                    <option value="round" <?php selected($settings['accessibe_trigger_shape'] ?? 'round', 'round'); ?>>Round (default)</option>
                                    <option value="square" <?php selected($settings['accessibe_trigger_shape'] ?? 'round', 'square'); ?>>Square</option>
                                </select>
                                <p class="description">Shape of the floating accessibility button.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Vertical Position</th>
                            <td>
                                <select name="tfm_plugin_settings[accessibe_position_y]" class="regular-text">
                                    <option value="bottom" <?php selected($settings['accessibe_position_y'] ?? 'bottom', 'bottom'); ?>>Bottom</option>
                                    <option value="center" <?php selected($settings['accessibe_position_y'] ?? 'bottom', 'center'); ?>>Center</option>
                                    <option value="top" <?php selected($settings['accessibe_position_y'] ?? 'bottom', 'top'); ?>>Top</option>
                                </select>
                                <p class="description">Vertical edge where the accessibility button appears.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Hide on Mobile</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[accessibe_hide_mobile]" value="1" <?php checked($settings['accessibe_hide_mobile'] ?? false, true); ?>>
                                    Hide the accessWidget button on mobile devices
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div id="tracking" class="tfm-tab-content">
                <div class="tfm-settings-section">
                    <h2>Tracking & Analytics</h2>
                    <p class="tfm-settings-description">Configure tracking and analytics integrations.</p>
                    <table class="form-table">
                        <tr>
                            <th>Enable WhatConverts</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[enable_whatconverts]" value="1" <?php checked($settings['enable_whatconverts'], true); ?>>
                                    Enable WhatConverts tracking
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>WhatConverts Account ID</th>
                            <td>
                                <input type="text" name="tfm_plugin_settings[whatconverts_account_id]" value="<?php echo esc_attr($settings['whatconverts_account_id'] ?? ''); ?>" class="regular-text" placeholder="Enter your WhatConverts account ID">
                                <p class="description">Enter your WhatConverts account ID (e.g., if your script URL is s.ksrndkehqnwntyxlhgto.com/123456.js, enter "123456").</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div id="logging" class="tfm-tab-content">
                <div class="tfm-settings-section">
                    <h2>Activity Logging</h2>
                    <p class="tfm-settings-description">Configure activity logging settings.</p>
                    <table class="form-table">
                        <tr>
                            <th>Enable Activity Logging</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[enable_logging]" value="1" <?php checked($settings['enable_logging'], true); ?>>
                                    Enable activity logging system
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>Log Level</th>
                            <td>
                                <?php $tfm_log_level = $settings['log_level'] ?? 'all'; ?>
                                <select name="tfm_plugin_settings[log_level]">
                                    <option value="all" <?php selected($tfm_log_level, 'all'); ?>>All activity (recommended)</option>
                                    <option value="important" <?php selected($tfm_log_level, 'important'); ?>>Important only (changes, deletions, failed logins)</option>
                                    <option value="critical" <?php selected($tfm_log_level, 'critical'); ?>>Critical only (deletions, role changes)</option>
                                </select>
                                <p class="description">How much to record. "All activity" captures logins, edits, media and plugin/theme changes; higher levels keep only the more serious events.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Log Retention</th>
                            <td>
                                <input type="number" name="tfm_plugin_settings[log_retention_days]" value="<?php echo esc_attr($settings['log_retention_days']); ?>" min="1" max="365" class="small-text">
                                <span>days</span>
                                <p class="description">Number of days to keep activity logs before automatic deletion.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div id="scripts" class="tfm-tab-content">
                <div class="tfm-settings-section">
                    <h2>Custom Scripts</h2>
                    <p class="tfm-settings-description">Add custom JavaScript code to your site's head or footer.</p>
                    <table class="form-table">
                        <tr>
                            <th>Custom Head Scripts</th>
                            <td>
                                <textarea name="tfm_plugin_settings[custom_head_scripts]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($settings['custom_head_scripts']); ?></textarea>
                                <p class="description">Add custom JavaScript or other scripts to be included in the <code>&lt;head&gt;</code> section.</p>
                                <ul class="description-list">
                                    <li>Include complete <code>&lt;script&gt;</code> tags with your code, or</li>
                                    <li>Just add JavaScript code without script tags (they will be added automatically)</li>
                                </ul>
                                <div class="tfm-code-example">Example with tags:<br><code>&lt;script src="https://example.com/script.js"&gt;&lt;/script&gt;</code></div>
                                <div class="tfm-code-example">Example without tags:<br><code>console.log('Hello World');</code></div>
                            </td>
                        </tr>
                        <tr>
                            <th>Custom Footer Scripts</th>
                            <td>
                                <textarea name="tfm_plugin_settings[custom_footer_scripts]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($settings['custom_footer_scripts']); ?></textarea>
                                <p class="description">Add custom JavaScript or other scripts to be included before the closing <code>&lt;/body&gt;</code> tag.</p>
                                <ul class="description-list">
                                    <li>Include complete <code>&lt;script&gt;</code> tags with your code, or</li>
                                    <li>Just add JavaScript code without script tags (they will be added automatically)</li>
                                </ul>
                                <div class="tfm-code-example">Example with tags:<br><code>&lt;script src="https://example.com/script.js"&gt;&lt;/script&gt;</code></div>
                                <div class="tfm-code-example">Example without tags:<br><code>console.log('Hello World');</code></div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div id="video_defer" class="tfm-tab-content">
                <?php do_action('tfm_render_settings_tab_video_defer'); ?>
            </div>

            <div id="lead_magnet" class="tfm-tab-content">
                <div class="tfm-settings-section">
                    <h2>Industry Outlook Lead Magnet</h2>
                    <p class="tfm-settings-description">Upload the promotional image and the downloadable file for the Industry Outlook lead magnet.</p>
                    <table class="form-table">
                        <tr>
                            <th>Lead Magnet Image</th>
                            <td>
                                <div style="margin-bottom:10px;">
                                    <img id="tfm-lm-image-preview" src="<?php echo $settings['lead_magnet']['image_id'] ? esc_url(wp_get_attachment_image_url($settings['lead_magnet']['image_id'], 'medium')) : ''; ?>" style="max-width:200px; height:auto; <?php echo $settings['lead_magnet']['image_id'] ? '' : 'display:none;'; ?>" alt="">
                                </div>
                                <input type="hidden" id="tfm-lm-image-id" name="tfm_plugin_settings[lead_magnet][image_id]" value="<?php echo esc_attr($settings['lead_magnet']['image_id']); ?>">
                                <button type="button" class="button" id="tfm-lm-image-upload">Select Image</button>
                                <button type="button" class="button" id="tfm-lm-image-remove" <?php echo $settings['lead_magnet']['image_id'] ? '' : 'style="display:none;"'; ?>>Remove</button>
                                <p class="description">Choose the image shown for the lead magnet.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Lead Magnet File</th>
                            <td>
                                <div style="margin-bottom:10px;" id="tfm-lm-file-info">
                                    <?php if (!empty($settings['lead_magnet']['file_id'])): ?>
                                        <?php $file_url = wp_get_attachment_url($settings['lead_magnet']['file_id']); ?>
                                        <a href="<?php echo esc_url($file_url); ?>" target="_blank"><?php echo esc_html(basename($file_url)); ?></a>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" id="tfm-lm-file-id" name="tfm_plugin_settings[lead_magnet][file_id]" value="<?php echo esc_attr($settings['lead_magnet']['file_id']); ?>">
                                <button type="button" class="button" id="tfm-lm-file-upload">Select File</button>
                                <button type="button" class="button" id="tfm-lm-file-remove" <?php echo $settings['lead_magnet']['file_id'] ? '' : 'style="display:none;"'; ?>>Remove</button>
                                <p class="description">Upload the downloadable file (e.g., PDF).</p>
                            </td>
                        </tr>
                    </table>
                    <div class="tfm-settings-section">
                        <h3>Lead Magnet Shortcodes</h3>
                        <p class="tfm-settings-description">Use these shortcodes in Elementor's Shortcode widget or HTML widget:</p>
                        <div class="tfm-code-example">
                            <strong>Lead Magnet Shortcodes:</strong><br>
                            <code>[lead_magnet_image]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[lead_magnet_image]">Copy</button> - Displays promotional image<br>
                            <code>[lead_magnet_link]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[lead_magnet_link]">Copy</button> - Creates complete download link<br>
                            <code>[lead_magnet_url]</code> <button type="button" class="button button-small tfm-copy-shortcode" data-shortcode="[lead_magnet_url]">Copy</button> - Outputs URL only (for Elementor buttons)
                        </div>
                    </div>
                </div>
            </div>

            <div id="html_sitemap" class="tfm-tab-content">
                <div class="tfm-settings-section">
                    <h2>HTML Sitemap</h2>
                    <p class="tfm-settings-description">Generate user-friendly HTML sitemaps for your website. Use the <code>[tfm_sitemap]</code> shortcode to display the sitemap on any page.</p>

                    <table class="form-table">
                        <tr>
                            <th>Enable HTML Sitemap</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[sitemap_enabled]" value="1" <?php checked($settings['sitemap_enabled'], true); ?>>
                                    Enable HTML sitemap functionality
                                </label>
                                <p class="description">When enabled, you can use the <code>[tfm_sitemap]</code> shortcode to display an HTML sitemap.</p>
                            </td>
                        </tr>

                        <tr>
                            <th>Include Post Types</th>
                            <td>
                                <?php
                                $post_types = get_post_types(['public' => true], 'objects');
                                $selected_types = isset($settings['sitemap_post_types']) ? (array) $settings['sitemap_post_types'] : ['page', 'post'];
                                ?>
                                <div class="tfm-post-types-list">
                                    <?php foreach ($post_types as $post_type): ?>
                                        <?php if ($post_type->name !== 'attachment'): // Skip attachments ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="checkbox"
                                                       name="tfm_plugin_settings[sitemap_post_types][]"
                                                       value="<?php echo esc_attr($post_type->name); ?>"
                                                       <?php checked(in_array($post_type->name, $selected_types)); ?>>
                                                <?php echo esc_html($post_type->label); ?>
                                                (<?php echo esc_html($post_type->name); ?>)
                                            </label>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description">Select which post types to include in the HTML sitemap.</p>
                            </td>
                        </tr>

                        <tr>
                            <th>Display Options</th>
                            <td>
                                <label style="display: block; margin-bottom: 10px;">
                                    <input type="checkbox" name="tfm_plugin_settings[sitemap_show_dates]" value="1" <?php checked($settings['sitemap_show_dates'], true); ?>>
                                    Show last modified dates
                                </label>
                                <label style="display: block; margin-bottom: 10px;">
                                    <input type="checkbox" name="tfm_plugin_settings[sitemap_show_counts]" value="1" <?php checked($settings['sitemap_show_counts'], true); ?>>
                                    Show post counts for categories
                                </label>
                                <label style="display: block;">
                                    <input type="checkbox" name="tfm_plugin_settings[sitemap_exclude_empty_cats]" value="1" <?php checked($settings['sitemap_exclude_empty_cats'], true); ?>>
                                    Exclude empty categories from display
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th>Cache Timeout</th>
                            <td>
                                <input type="number" name="tfm_plugin_settings[sitemap_cache_timeout]" value="<?php echo esc_attr($settings['sitemap_cache_timeout']); ?>" class="small-text" min="300" max="86400" step="300">
                                seconds
                                <p class="description">How long to cache the sitemap (minimum 300 seconds / 5 minutes, maximum 86400 seconds / 24 hours). Default: 3600 seconds (1 hour).</p>
                            </td>
                        </tr>
                    </table>

                    <div class="tfm-shortcode-info" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #007cba; margin-top: 20px;">
                        <h3 style="margin-top: 0; color: #007cba;">Shortcode Usage</h3>
                        <p><strong>Basic usage:</strong> <code>[tfm_sitemap]</code></p>
                        <p><strong>With parameters:</strong> <code>[tfm_sitemap post_types="page,post" show_dates="false" show_counts="true"]</code></p>
                        <p><strong>Parameters:</strong></p>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li><code>post_types</code> - Comma-separated list of post types to include</li>
                            <li><code>show_dates</code> - Show last modified dates (true/false)</li>
                            <li><code>show_counts</code> - Show post counts for categories (true/false)</li>
                            <li><code>exclude_empty_cats</code> - Exclude categories with no posts (true/false)</li>
                        </ul>

                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <h4 style="margin: 0 0 10px 0; color: #007cba;">Debug & Testing</h4>
                            <p style="margin: 0 0 10px 0;">Test your sitemap configuration and clear cache:</p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=tfm-sitemap-debug')); ?>" class="button button-secondary">
                                🧪 Open Sitemap Debug Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div id="debug_debloat" class="tfm-tab-content">
                <div class="tfm-settings-section">
                    <h2>WordPress Debug & Debloat</h2>
                    <p class="tfm-settings-description">Optimize WordPress performance and reduce clutter by controlling revisions and disabling unnecessary features.</p>

                    <table class="form-table">
                        <tr>
                            <th>Disable WordPress Revisions</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[disable_wp_revisions]" value="1" <?php checked($settings['disable_wp_revisions'], true); ?> data-target="#wp_post_revisions_limit_row">
                                    Completely disable WordPress post revisions
                                </label>
                                <p class="description">Disabling revisions can significantly reduce database size, but you will lose the ability to revert changes to posts and pages.</p>

                                <?php if (!$settings['disable_wp_revisions']): ?>
                                <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                                    <h4 style="margin: 0 0 10px 0; color: #856404;">Revision Cleanup Tools</h4>
                                    <p style="margin: 0 0 15px 0; font-size: 13px; color: #856404;">
                                        <strong>Warning:</strong> These operations permanently delete revision data and cannot be undone.
                                    </p>

                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <button type="button" id="tfm-cleanup-revisions" class="button button-secondary" style="background: #dc3545; border-color: #dc3545; color: white;">
                                            🗑️ Clean Up Revisions
                                        </button>
                                        <span id="tfm-cleanup-status" style="font-size: 12px; color: #6c757d;"></span>
                                    </div>

                                    <div id="tfm-cleanup-details" style="margin-top: 10px; display: none;">
                                        <div style="font-size: 12px; color: #495057; background: white; padding: 10px; border-radius: 3px; border: 1px solid #dee2e6;">
                                            <div id="tfm-cleanup-stats"></div>
                                            <div style="margin-top: 10px;">
                                                <label style="display: block; margin-bottom: 5px;">
                                                    <input type="radio" name="tfm_cleanup_type" value="excess" checked>
                                                    Remove excess revisions (keep <?php echo esc_html($settings['wp_post_revisions_limit'] ?: 5); ?> per post)
                                                </label>
                                                <label style="display: block; margin-bottom: 5px;">
                                                    <input type="radio" name="tfm_cleanup_type" value="old">
                                                    Remove revisions older than <input type="number" id="tfm_cleanup_days" value="30" min="1" max="365" style="width: 60px;"> days
                                                </label>
                                                <label style="display: block;">
                                                    <input type="radio" name="tfm_cleanup_type" value="all">
                                                    <span style="color: #dc3545; font-weight: bold;">Remove ALL revisions (destructive!)</span>
                                                </label>
                                            </div>
                                            <div style="margin-top: 10px; text-align: right;">
                                                <button type="button" id="tfm-confirm-cleanup" class="button button-primary" style="background: #dc3545; border-color: #dc3545;">Confirm Delete</button>
                                                <button type="button" id="tfm-cancel-cleanup" class="button">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr id="wp_post_revisions_limit_row">
                            <th>Limit Post Revisions</th>
                            <td>
                                <input type="number" name="tfm_plugin_settings[wp_post_revisions_limit]" value="<?php echo esc_attr($settings['wp_post_revisions_limit']); ?>" min="0" class="small-text" <?php disabled($settings['disable_wp_revisions'], true); ?> >
                                revisions
                                <p class="description">Set the maximum number of revisions to keep for each post/page. Set to 0 to disable (same as above).</p>
                            </td>
                        </tr>
                    </table>

                    <h3>Core WordPress Debloat Options</h3>
                    <table class="form-table">
                        <tr>
                            <th>Disable Emojis</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[disable_emojis]" value="1" <?php checked($settings['disable_emojis'], true); ?>>
                                    Remove WordPress built-in Emoji JavaScript and CSS
                                </label>
                                <p class="description">Disables WordPress's emoji script, which can slightly improve page load times. Emojis will still work if supported by the user's browser.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Disable jQuery Migrate</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[disable_jquery_migrate]" value="1" <?php checked($settings['disable_jquery_migrate'], true); ?>>
                                    Prevent jQuery Migrate script from loading
                                </label>
                                <p class="description">jQuery Migrate is used for backward compatibility with older jQuery code. Disabling it can resolve conflicts and improve performance if your theme/plugins don't rely on deprecated jQuery features.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Disable oEmbeds</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="tfm_plugin_settings[disable_oembeds]" value="1" <?php checked($settings['disable_oembeds'], true); ?>>
                                    Stop WordPress from automatically embedding content
                                </label>
                                <p class="description">Prevents WordPress from automatically converting URLs from sites like YouTube, Twitter, etc., into embedded content. This can reduce external requests and improve privacy.</p>
                            </td>
                        </tr>
                    </table>

                    <h3>Environment Information</h3>
                    <p class="tfm-settings-description">System information that can help with debugging and troubleshooting.</p>

                    <div class="tfm-environment-info" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 20px; margin: 20px 0;">
                        <table class="widefat striped" style="margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th style="padding: 12px; background: #f1f1f1; border-bottom: 2px solid #ddd;">Component</th>
                                    <th style="padding: 12px; background: #f1f1f1; border-bottom: 2px solid #ddd;">Current Value</th>
                                    <th style="padding: 12px; background: #f1f1f1; border-bottom: 2px solid #ddd;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>PHP Version</strong></td>
                                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                                    <td><?php
                                        $php_version = PHP_VERSION;
                                        $status_color = version_compare($php_version, '7.4', '>=') ? '#28a745' : (version_compare($php_version, '7.0', '>=') ? '#ffc107' : '#dc3545');
                                        $status_text = version_compare($php_version, '7.4', '>=') ? 'Excellent' : (version_compare($php_version, '7.0', '>=') ? 'Good' : 'Outdated');
                                        echo '<span style="color: ' . $status_color . '; font-weight: bold;">' . $status_text . '</span>';
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>WordPress Version</strong></td>
                                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                                    <td><?php
                                        $wp_version = get_bloginfo('version');
                                        $latest_stable = '6.7'; // Update this as needed
                                        $status = version_compare($wp_version, $latest_stable, '>=') ? '<span style="color: #28a745; font-weight: bold;">Up to Date</span>' : '<span style="color: #ffc107; font-weight: bold;">Update Available</span>';
                                        echo $status;
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Server Software</strong></td>
                                    <td><?php echo esc_html($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></td>
                                    <td><?php
                                        $server = $_SERVER['SERVER_SOFTWARE'] ?? '';
                                        if (stripos($server, 'nginx') !== false) {
                                            echo '<span style="color: #28a745; font-weight: bold;">Nginx</span>';
                                        } elseif (stripos($server, 'apache') !== false) {
                                            echo '<span style="color: #28a745; font-weight: bold;">Apache</span>';
                                        } else {
                                            echo '<span style="color: #6c757d;">Other</span>';
                                        }
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Memory Limit</strong></td>
                                    <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                                    <td><?php
                                        $memory_limit = ini_get('memory_limit');
                                        $memory_bytes = wp_convert_hr_to_bytes($memory_limit);
                                        $status = $memory_bytes >= 134217728 ? '<span style="color: #28a745; font-weight: bold;">Good</span>' : ($memory_bytes >= 67108864 ? '<span style="color: #ffc107; font-weight: bold;">Fair</span>' : '<span style="color: #dc3545; font-weight: bold;">Low</span>');
                                        echo $status;
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Max Execution Time</strong></td>
                                    <td><?php echo esc_html(ini_get('max_execution_time')); ?> seconds</td>
                                    <td><?php
                                        $exec_time = (int) ini_get('max_execution_time');
                                        $status = $exec_time >= 120 ? '<span style="color: #28a745; font-weight: bold;">Good</span>' : ($exec_time >= 30 ? '<span style="color: #ffc107; font-weight: bold;">Fair</span>' : '<span style="color: #dc3545; font-weight: bold;">Low</span>');
                                        echo $status;
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Upload Max Filesize</strong></td>
                                    <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
                                    <td><?php
                                        $upload_limit = ini_get('upload_max_filesize');
                                        $upload_bytes = wp_convert_hr_to_bytes($upload_limit);
                                        $status = $upload_bytes >= 33554432 ? '<span style="color: #28a745; font-weight: bold;">Good</span>' : ($upload_bytes >= 8388608 ? '<span style="color: #ffc107; font-weight: bold;">Fair</span>' : '<span style="color: #dc3545; font-weight: bold;">Low</span>');
                                        echo $status;
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Post Max Size</strong></td>
                                    <td><?php echo esc_html(ini_get('post_max_size')); ?></td>
                                    <td><?php
                                        $post_max = ini_get('post_max_size');
                                        $post_bytes = wp_convert_hr_to_bytes($post_max);
                                        $status = $post_bytes >= 67108864 ? '<span style="color: #28a745; font-weight: bold;">Good</span>' : ($post_bytes >= 16777216 ? '<span style="color: #ffc107; font-weight: bold;">Fair</span>' : '<span style="color: #dc3545; font-weight: bold;">Low</span>');
                                        echo $status;
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>MySQL Version</strong></td>
                                    <td><?php
                                        global $wpdb;
                                        $mysql_version = $wpdb->get_var("SELECT VERSION() as version");
                                        echo esc_html($mysql_version);
                                    ?></td>
                                    <td><?php
                                        $mysql_version = $wpdb->get_var("SELECT VERSION() as version");
                                        $version_parts = explode('.', $mysql_version);
                                        $major_minor = $version_parts[0] . '.' . $version_parts[1];
                                        $status = version_compare($major_minor, '8.0', '>=') ? '<span style="color: #28a745; font-weight: bold;">Excellent</span>' : (version_compare($major_minor, '5.7', '>=') ? '<span style="color: #ffc107; font-weight: bold;">Good</span>' : '<span style="color: #dc3545; font-weight: bold;">Outdated</span>');
                                        echo $status;
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Active Plugins</strong></td>
                                    <td><?php
                                        $active_plugins = get_option('active_plugins', []);
                                        echo count($active_plugins) . ' active';
                                    ?></td>
                                    <td><?php
                                        $plugin_count = count(get_option('active_plugins', []));
                                        $status = $plugin_count <= 20 ? '<span style="color: #28a745; font-weight: bold;">Optimal</span>' : ($plugin_count <= 40 ? '<span style="color: #ffc107; font-weight: bold;">Moderate</span>' : '<span style="color: #dc3545; font-weight: bold;">High</span>');
                                        echo $status;
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Active Theme</strong></td>
                                    <td><?php
                                        $theme = wp_get_theme();
                                        echo esc_html($theme->get('Name') . ' ' . $theme->get('Version'));
                                    ?></td>
                                    <td><?php
                                        $theme = wp_get_theme();
                                        $parent_theme = $theme->parent();
                                        if ($parent_theme) {
                                            echo '<span style="color: #6c757d;">Child Theme</span>';
                                        } else {
                                            echo '<span style="color: #28a745; font-weight: bold;">Parent Theme</span>';
                                        }
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>PHP Extensions</strong></td>
                                    <td><?php
                                        $required_extensions = ['curl', 'gd', 'mbstring', 'mysql', 'openssl', 'xml'];
                                        $loaded_extensions = get_loaded_extensions();
                                        $missing = array_diff($required_extensions, $loaded_extensions);
                                        $available = array_intersect($required_extensions, $loaded_extensions);
                                        echo count($available) . '/' . count($required_extensions) . ' required loaded';
                                        if (!empty($missing)) {
                                            echo ' <small style="color: #dc3545;">(Missing: ' . implode(', ', $missing) . ')</small>';
                                        }
                                    ?></td>
                                    <td><?php
                                        $required_extensions = ['curl', 'gd', 'mbstring', 'mysql', 'openssl', 'xml'];
                                        $loaded_extensions = get_loaded_extensions();
                                        $available = array_intersect($required_extensions, $loaded_extensions);
                                        $status = count($available) === count($required_extensions) ? '<span style="color: #28a745; font-weight: bold;">Complete</span>' : '<span style="color: #dc3545; font-weight: bold;">Incomplete</span>';
                                        echo $status;
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Debug Mode</strong></td>
                                    <td><?php echo WP_DEBUG ? 'Enabled' : 'Disabled'; ?></td>
                                    <td><?php
                                        $status = WP_DEBUG ? '<span style="color: #ffc107; font-weight: bold;">Debug On</span>' : '<span style="color: #28a745; font-weight: bold;">Production</span>';
                                        echo $status;
                                    ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <div style="margin-top: 15px; padding: 10px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #495057;">System Paths</h4>
                            <div style="font-family: monospace; font-size: 12px; color: #6c757d;">
                                <strong>WordPress Root:</strong> <?php echo esc_html(ABSPATH); ?><br>
                                <strong>Content Directory:</strong> <?php echo esc_html(WP_CONTENT_DIR); ?><br>
                                <strong>Uploads Directory:</strong> <?php echo esc_html(wp_upload_dir()['basedir']); ?><br>
                                <strong>Theme Directory:</strong> <?php echo esc_html(get_template_directory()); ?><br>
                                <strong>Plugin Directory:</strong> <?php echo esc_html(WP_PLUGIN_DIR); ?>
                            </div>
                        </div>

                        <div style="margin-top: 15px; padding: 10px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #495057;">TFM Plugin Status</h4>
                            <div style="font-family: monospace; font-size: 12px; color: #6c757d;">
                                <strong>Plugin Version:</strong> <?php echo esc_html(TFM_PLUGIN_VERSION); ?><br>
                                <strong>Plugin File:</strong> <?php echo esc_html(TFM_PLUGIN_DIR . 'topfiremedia.php'); ?><br>
                                <strong>Settings Saved:</strong> <?php
                                    $tfm_settings = get_option('tfm_plugin_settings', []);
                                    echo !empty($tfm_settings) ? '<span style="color: #28a745;">Yes (' . count($tfm_settings) . ' settings)</span>' : '<span style="color: #dc3545;">No settings saved</span>';
                                ?><br>
                                <strong>Available Hooks:</strong> <?php
                                    global $wp_filter;
                                    $tfm_hooks = 0;
                                    foreach ($wp_filter as $hook_name => $hook_data) {
                                        if (strpos($hook_name, 'tfm_') === 0) {
                                            $tfm_hooks++;
                                        }
                                    }
                                    echo $tfm_hooks . ' TFM hooks registered';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tfm-submit-section">
                <?php submit_button(); ?>
            </div>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Tab functionality
        $('.tfm-tabs a').click(function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            // Update active tab
            $('.tfm-tabs a').removeClass('active');
            $(this).addClass('active');
            
            // Show target content
            $('.tfm-tab-content').removeClass('active');
            $(target).addClass('active');

            // Persist in hash
            if (history.replaceState) {
                history.replaceState(null, null, target);
            } else {
                location.hash = target;
            }
        });

        // Load tab from hash if present
        if (location.hash && $('.tfm-tabs a[href="' + location.hash + '"]').length) {
            var $link = $('.tfm-tabs a[href="' + location.hash + '"]');
            $('.tfm-tabs a').removeClass('active');
            $link.addClass('active');
            $('.tfm-tab-content').removeClass('active');
            $(location.hash).addClass('active');
        }


        // Copy shortcode functionality
        $('.tfm-copy-shortcode').on('click', function(e) {
            e.preventDefault();
            var shortcode = $(this).data('shortcode');
            var $button = $(this);
            
            // Try to copy to clipboard
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(shortcode).then(function() {
                    $button.text('Copied!').addClass('button-primary');
                    setTimeout(function() {
                        $button.text('Copy').removeClass('button-primary');
                    }, 2000);
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = shortcode;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    $button.text('Copied!').addClass('button-primary');
                    setTimeout(function() {
                        $button.text('Copy').removeClass('button-primary');
                    }, 2000);
                } catch (err) {
                    alert('Unable to copy to clipboard');
                }
                
                document.body.removeChild(textArea);
            }
        });

        // Media uploaders for Lead Magnet and Login Logo
        let lmImageFrame, lmFileFrame, loginLogoFrame;
        $('#tfm-lm-image-upload').on('click', function(e) {
            e.preventDefault();
            if (lmImageFrame) { lmImageFrame.open(); return; }
            lmImageFrame = wp.media({ title: 'Select Lead Magnet Image', button: { text: 'Use Image' }, multiple: false, library: { type: ['image'] } });
            lmImageFrame.on('select', function() {
                const attachment = lmImageFrame.state().get('selection').first().toJSON();
                $('#tfm-lm-image-id').val(attachment.id);
                $('#tfm-lm-image-preview').attr('src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url).show();
                $('#tfm-lm-image-remove').show();
            });
            lmImageFrame.open();
        });
        $('#tfm-lm-image-remove').on('click', function(e) {
            e.preventDefault();
            $('#tfm-lm-image-id').val('');
            $('#tfm-lm-image-preview').hide().attr('src', '');
            $(this).hide();
        });

        $('#tfm-lm-file-upload').on('click', function(e) {
            e.preventDefault();
            if (lmFileFrame) { lmFileFrame.open(); return; }
            lmFileFrame = wp.media({ title: 'Select Lead Magnet File', button: { text: 'Use File' }, multiple: false });
            lmFileFrame.on('select', function() {
                const attachment = lmFileFrame.state().get('selection').first().toJSON();
                $('#tfm-lm-file-id').val(attachment.id);
                const link = $('<a>').attr('href', attachment.url).attr('target', '_blank').text(attachment.filename);
                $('#tfm-lm-file-info').html(link);
                $('#tfm-lm-file-remove').show();
            });
            lmFileFrame.open();
        });
        $('#tfm-lm-file-remove').on('click', function(e) {
            e.preventDefault();
            $('#tfm-lm-file-id').val('');
            $('#tfm-lm-file-info').empty();
            $(this).hide();
        });

        // Login Logo uploader
        $('#tfm-login-logo-upload').on('click', function(e) {
            e.preventDefault();
            if (loginLogoFrame) { loginLogoFrame.open(); return; }
            loginLogoFrame = wp.media({ title: 'Select Login Logo', button: { text: 'Use Logo' }, multiple: false, library: { type: ['image'] } });
            loginLogoFrame.on('select', function() {
                const attachment = loginLogoFrame.state().get('selection').first().toJSON();
                $('#tfm-login-logo-url').val(attachment.url);
                $('#tfm-login-logo-preview').attr('src', attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url).show();
                $('#tfm-login-logo-remove').show();
            });
            loginLogoFrame.open();
        });
        $('#tfm-login-logo-remove').on('click', function(e) {
            e.preventDefault();
            $('#tfm-login-logo-url').val('');
            $('#tfm-login-logo-preview').hide().attr('src', '');
            $(this).hide();
        });

        // Revision cleanup functionality
        $('#tfm-cleanup-revisions').on('click', function(e) {
            e.preventDefault();

            // Get current revision stats first
            $('#tfm-cleanup-status').html('Loading revision statistics...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tfm_get_revision_stats',
                    nonce: '<?php echo wp_create_nonce("tfm_revision_cleanup"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#tfm-cleanup-stats').html(
                            '<strong>Current Statistics:</strong><br>' +
                            '• Total revisions: ' + response.data.total_revisions.toLocaleString() + '<br>' +
                            '• Total posts with revisions: ' + response.data.posts_with_revisions.toLocaleString() + '<br>' +
                            '• Database size impact: ~' + response.data.estimated_size + ' MB'
                        );
                        $('#tfm-cleanup-details').slideDown();
                        $('#tfm-cleanup-status').html('');
                    } else {
                        $('#tfm-cleanup-status').html('<span style="color: #dc3545;">Error loading statistics</span>');
                    }
                },
                error: function() {
                    $('#tfm-cleanup-status').html('<span style="color: #dc3545;">Failed to load statistics</span>');
                }
            });
        });

        $('#tfm-confirm-cleanup').on('click', function(e) {
            e.preventDefault();

            var cleanupType = $('input[name="tfm_cleanup_type"]:checked').val();
            var days = $('#tfm_cleanup_days').val();

            if (!confirm('Are you absolutely sure you want to delete revisions? This action cannot be undone!')) {
                return;
            }

            $('#tfm-cleanup-status').html('Deleting revisions... Please wait...');
            $(this).prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tfm_cleanup_revisions',
                    cleanup_type: cleanupType,
                    days: days,
                    nonce: '<?php echo wp_create_nonce("tfm_revision_cleanup"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#tfm-cleanup-status').html(
                            '<span style="color: #28a745;">Success! Deleted ' +
                            response.data.deleted_count.toLocaleString() + ' revisions</span>'
                        );
                        $('#tfm-cleanup-details').slideUp();
                    } else {
                        $('#tfm-cleanup-status').html('<span style="color: #dc3545;">Error: ' + response.data.message + '</span>');
                    }
                    $('#tfm-confirm-cleanup').prop('disabled', false);
                },
                error: function() {
                    $('#tfm-cleanup-status').html('<span style="color: #dc3545;">Network error occurred</span>');
                    $('#tfm-confirm-cleanup').prop('disabled', false);
                }
            });
        });

        $('#tfm-cancel-cleanup').on('click', function(e) {
            e.preventDefault();
            $('#tfm-cleanup-details').slideUp();
            $('#tfm-cleanup-status').html('');
        });

    });
    </script>

    <!-- HTML Sitemap Styles -->
    <style>
        /* HTML Sitemap Styles for frontend */
        .tfm-html-sitemap {
            margin: 20px 0;
        }
        .tfm-html-sitemap h2 {
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 10px;
            margin: 30px 0 15px 0;
        }
        .tfm-html-sitemap h3 {
            color: #555;
            margin: 20px 0 10px 0;
            font-size: 1.1em;
        }
        .tfm-html-sitemap ul {
            margin: 0 0 20px 0;
            padding-left: 20px;
        }
        .tfm-html-sitemap li {
            margin: 5px 0;
            line-height: 1.4;
        }
        .tfm-html-sitemap a {
            color: #007cba;
            text-decoration: none;
        }
        .tfm-html-sitemap a:hover {
            text-decoration: underline;
        }
        .tfm-sitemap-date {
            color: #666;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .tfm-sitemap-count {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        .tfm-html-sitemap .children {
            margin-left: 20px;
        }
    </style>
    <?php
}

function tfm_get_log_templates() {
    static $templates = null;
    if ($templates !== null) {
        return $templates;
    }

    $templates = [
        'user_login' => [
            'label' => 'User signed in',
            'icon' => 'dashicons-unlock',
            'severity' => 'success',
            'message' => '{user_login} signed in successfully',
            'context' => [
                'Role' => '{user_role}',
                'IP' => '{ip_address}',
            ],
        ],
        'user_logout' => [
            'label' => 'User signed out',
            'icon' => 'dashicons-lock',
            'severity' => 'info',
            'message' => '{user_login} signed out',
            'context' => [
                'Role' => '{user_role}',
                'IP' => '{ip_address}',
            ],
        ],
        'user_register' => [
            'label' => 'New user registered',
            'icon' => 'dashicons-admin-users',
            'severity' => 'success',
            'message' => 'Account created for {user_login}',
            'context' => [
                'Email' => '{user_email}',
                'Role' => '{user_role}',
            ],
        ],
        'user_profile_update' => [
            'label' => 'User profile updated',
            'icon' => 'dashicons-admin-users',
            'severity' => 'info',
            'message' => '{user_login} updated their profile',
            'context' => [
                'User ID' => '{user_id}',
            ],
        ],
        'post_published' => [
            'label' => 'Post published',
            'icon' => 'dashicons-admin-post',
            'severity' => 'success',
            'message' => '"{post_title}" published as {post_status}',
            'context' => [
                'Type' => '{post_type}',
                'Author ID' => '{post_author}',
            ],
        ],
        'post_updated' => [
            'label' => 'Content edited',
            'icon' => 'dashicons-edit',
            'severity' => 'info',
            'message' => '"{post_title}" edited ({changed})',
            'context' => [
                'Type' => '{post_type}',
                'Author ID' => '{post_author}',
            ],
        ],
        'post_deleted' => [
            'label' => 'Content permanently deleted',
            'icon' => 'dashicons-trash',
            'severity' => 'danger',
            'message' => '"{post_title}" permanently deleted',
            'context' => [
                'Type' => '{post_type}',
                'Author ID' => '{post_author}',
            ],
        ],
        'page_published' => [
            'label' => 'Page published',
            'icon' => 'dashicons-admin-page',
            'severity' => 'success',
            'message' => '"{page_title}" published as {page_status}',
            'context' => [
                'Author ID' => '{page_author}',
            ],
        ],
        'media_uploaded' => [
            'label' => 'Media uploaded',
            'icon' => 'dashicons-format-image',
            'severity' => 'info',
            'message' => '"{file_name}" uploaded ({file_type})',
            'context' => [
                'Attachment ID' => '{attachment_id}',
                'Uploaded by' => '{uploaded_by}',
            ],
        ],
        'media_deleted' => [
            'label' => 'Media deleted',
            'icon' => 'dashicons-dismiss',
            'severity' => 'warning',
            'message' => '"{file_name}" deleted',
            'context' => [
                'Attachment ID' => '{attachment_id}',
                'Deleted by' => '{deleted_by}',
            ],
        ],
        'comment_posted' => [
            'label' => 'Comment posted',
            'icon' => 'dashicons-admin-comments',
            'severity' => 'info',
            'message' => 'Comment #{comment_id} on post #{post_id}',
            'context' => [
                'Author' => '{comment_author}',
                'Email' => '{comment_author_email}',
            ],
        ],
        'comment_deleted' => [
            'label' => 'Comment deleted',
            'icon' => 'dashicons-admin-comments',
            'severity' => 'warning',
            'message' => 'Comment #{comment_id} deleted from post #{post_id}',
            'context' => [
                'Deleted by' => '{deleted_by}',
            ],
        ],
        'plugin_activated' => [
            'label' => 'Plugin activated',
            'icon' => 'dashicons-admin-plugins',
            'severity' => 'success',
            'message' => '{plugin} activated',
            'context' => [
                'Triggered by' => '{activated_by}',
            ],
        ],
        'plugin_deactivated' => [
            'label' => 'Plugin deactivated',
            'icon' => 'dashicons-admin-plugins',
            'severity' => 'warning',
            'message' => '{plugin} deactivated',
            'context' => [
                'Triggered by' => '{deactivated_by}',
            ],
        ],
        'plugin_deleted' => [
            'label' => 'Plugin deleted',
            'icon' => 'dashicons-admin-plugins',
            'severity' => 'danger',
            'message' => '{plugin} deleted from site',
            'context' => [],
        ],
        'theme_switched' => [
            'label' => 'Theme switched',
            'icon' => 'dashicons-admin-appearance',
            'severity' => 'warning',
            'message' => 'Theme switched to {new_theme}',
            'context' => [
                'Stylesheet' => '{theme_object}',
            ],
        ],
        'widget_updated' => [
            'label' => 'Widget updated',
            'icon' => 'dashicons-screenoptions',
            'severity' => 'info',
            'message' => '{widget_name} updated',
            'context' => [
                'Widget ID' => '{widget_id}',
                'Updated by' => '{updated_by}',
            ],
        ],
        'menu_updated' => [
            'label' => 'Navigation updated',
            'icon' => 'dashicons-menu',
            'severity' => 'info',
            'message' => 'Menu "{menu_name}" updated',
            'context' => [
                'Menu ID' => '{menu_id}',
                'Updated by' => '{updated_by}',
            ],
        ],
        'option_updated' => [
            'label' => 'Site setting changed',
            'icon' => 'dashicons-admin-settings',
            'severity' => 'warning',
            'message' => 'Setting "{option_name}" changed',
            'context' => [
                'From' => '{old_value}',
                'To' => '{new_value}',
            ],
        ],
        'user_login_failed' => [
            'label' => 'Failed login attempt',
            'icon' => 'dashicons-warning',
            'severity' => 'warning',
            'message' => 'Failed login for "{attempted_username}"',
            'context' => [
                'IP' => '{ip_address}',
            ],
        ],
        'user_role_changed' => [
            'label' => 'User role changed',
            'icon' => 'dashicons-admin-users',
            'severity' => 'danger',
            'message' => '{user_login} role changed to {new_role}',
            'context' => [
                'Previous role(s)' => '{old_roles}',
                'User ID' => '{user_id}',
            ],
        ],
        'user_deleted' => [
            'label' => 'User deleted',
            'icon' => 'dashicons-no',
            'severity' => 'danger',
            'message' => 'User "{user_login}" deleted',
            'context' => [
                'Email' => '{user_email}',
                'Content reassigned to' => '{reassigned_to}',
            ],
        ],
        'post_trashed' => [
            'label' => 'Content trashed',
            'icon' => 'dashicons-trash',
            'severity' => 'warning',
            'message' => '"{post_title}" moved to trash',
            'context' => [
                'Type' => '{post_type}',
            ],
        ],
        'post_status_changed' => [
            'label' => 'Content status changed',
            'icon' => 'dashicons-update',
            'severity' => 'info',
            'message' => '"{post_title}" ({old_status} → {new_status})',
            'context' => [
                'Type' => '{post_type}',
            ],
        ],
        'plugin_updated' => [
            'label' => 'Plugin installed/updated',
            'icon' => 'dashicons-admin-plugins',
            'severity' => 'info',
            'message' => 'Plugin {action}: {items}',
            'context' => [],
        ],
        'theme_updated' => [
            'label' => 'Theme installed/updated',
            'icon' => 'dashicons-admin-appearance',
            'severity' => 'info',
            'message' => 'Theme {action}: {items}',
            'context' => [],
        ],
        'core_updated' => [
            'label' => 'WordPress core updated',
            'icon' => 'dashicons-wordpress',
            'severity' => 'warning',
            'message' => 'WordPress core {action}',
            'context' => [],
        ],
        '__default' => [
            'label' => 'Activity recorded',
            'icon' => 'dashicons-info-outline',
            'severity' => 'info',
            'message' => '{action} event captured',
            'context' => [
                'IP' => '{ip_address}',
            ],
        ],
    ];

    return $templates;
}

function tfm_interpolate_log_template($template, $context) {
    return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($context) {
        $key = $matches[1];
        return isset($context[$key]) ? (string) $context[$key] : '';
    }, $template);
}

function tfm_format_log_details_markup($log) {
    $details = [
        'User ID' => $log['user_id'] ?? '',
        'Username' => $log['user_login'] ?? '',
        'Display Name' => $log['user_display_name'] ?? '',
        'User Email' => $log['user_email'] ?? '',
        'User Role' => $log['user_role'] ?? '',
        'IP Address' => $log['ip_address'] ?? '',
        'User Agent' => $log['user_agent'] ?? '',
        'Action' => $log['action'] ?? '',
    ];

    $payload = '';
    if (!empty($log['data'])) {
        $payload = wp_json_encode($log['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    ob_start();
    ?>
    <div class="tfm-log-details-grid">
        <?php foreach ($details as $label => $value): ?>
            <?php if ($value === '' || $value === null) continue; ?>
            <div>
                <div class="tfm-log-detail-label"><?php echo esc_html($label); ?></div>
                <div class="tfm-log-detail-value"><?php echo esc_html($value); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($payload)): ?>
        <pre class="tfm-log-payload"><?php echo esc_html($payload); ?></pre>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

function tfm_format_log_entry_for_view($log, $index) {
    $templates = tfm_get_log_templates();
    $action = $log['action'] ?? 'unknown';
    $template = $templates[$action] ?? $templates['__default'];

    $context = array_merge(
        [
            'action' => $action,
            'user_login' => $log['user_login'] ?? '',
            'user_role' => $log['user_role'] ?? '',
            'user_email' => $log['user_email'] ?? '',
            'user_id' => $log['user_id'] ?? '',
            'ip_address' => $log['ip_address'] ?? '',
        ],
        is_array($log['data'] ?? null) ? $log['data'] : []
    );

    $message = tfm_interpolate_log_template($template['message'], $context);
    $badges = [];

    if (!empty($template['context'])) {
        foreach ($template['context'] as $label => $value_template) {
            $value = trim(tfm_interpolate_log_template($value_template, $context));
            if ($value !== '') {
                $badges[] = [
                    'label' => $label,
                    'value' => $value
                ];
            }
        }
    }

    $timestamp = isset($log['timestamp']) ? strtotime($log['timestamp']) : 0;
    $human_time = $timestamp ? wp_date('M j, Y g:i a', $timestamp) : '';
    $relative = $timestamp ? human_time_diff($timestamp, current_time('timestamp')) . ' ago' : '';

    // Prefer display name, then the login/context (e.g. 'cron', 'rest'), then a fallback.
    if (!empty($log['user_display_name'])) {
        $user_display = $log['user_display_name'];
    } elseif (!empty($log['user_login'])) {
        $user_display = $log['user_login'];
    } else {
        $user_display = 'System';
    }
    $user_meta_parts = [];
    if (!empty($log['user_role'])) {
        $user_meta_parts[] = ucwords(str_replace('_', ' ', $log['user_role']));
    }
    if (!empty($log['user_email'])) {
        $user_meta_parts[] = $log['user_email'];
    }

    $source_parts = [];
    if (!empty($log['ip_address'])) {
        $source_parts[] = $log['ip_address'];
    }
    if (!empty($log['user_agent'])) {
        $source_parts[] = mb_strimwidth($log['user_agent'], 0, 80, '…');
    }

    return [
        'action' => $action,
        'id' => 'tfm-log-details-' . $index,
        'icon' => $template['icon'],
        'label' => $template['label'],
        // Prefer the severity stored at write time; fall back to the template
        // (for entries logged before severity was stored).
        'severity' => $log['severity'] ?? $template['severity'],
        'message' => $message,
        'context_badges' => $badges,
        'timestamp_order' => $timestamp,
        'timestamp_human' => $human_time,
        'timestamp_relative' => $relative,
        'user_display' => $user_display,
        'user_meta' => implode(' · ', array_filter($user_meta_parts)),
        'source' => implode(' · ', array_filter($source_parts)),
        'details_html' => tfm_format_log_details_markup($log),
    ];
}

function tfm_prepare_log_rows($logs) {
    $rows = [];
    foreach ($logs as $index => $log) {
        $rows[] = tfm_format_log_entry_for_view($log, $index);
    }
    return $rows;
}

function tfm_format_bytes($bytes) {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
}

function tfm_collect_log_stats($logs, $tfm_logger) {
    $latest_timestamp = 0;
    if (!empty($logs) && !empty($logs[0]['timestamp'])) {
        $latest_timestamp = strtotime($logs[0]['timestamp']);
    }

    $current_file_size = 0;
    $log_file = ($tfm_logger && method_exists($tfm_logger, 'get_current_log_file')) ? $tfm_logger->get_current_log_file() : '';
    if (!empty($log_file) && file_exists($log_file)) {
        $current_file_size = filesize($log_file);
    }

    $log_dir = ($tfm_logger && method_exists($tfm_logger, 'get_log_directory')) ? $tfm_logger->get_log_directory() : '';

    return [
        'total' => count($logs),
        'latest' => $latest_timestamp ? wp_date('M j, Y g:i a', $latest_timestamp) : '—',
        'file_size' => tfm_format_bytes($current_file_size),
        'path' => $log_dir,
    ];
}

function tfm_render_news_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $settings = tfm_load_settings();
    ?>
    <div class="wrap tfm-settings-wrap">
        <h1>TFM News Settings</h1>

        <form method="post" action="options.php">
            <?php settings_fields('tfm_plugin_settings_group'); ?>
            <input type="hidden" name="tfm_plugin_settings[_settings_context]" value="news_only">

            <div class="tfm-settings-section">
                <h2>News Feature</h2>
                <p class="tfm-settings-description">Enable a dedicated News content type for outbound article cards and Elementor-driven news layouts.</p>

                <table class="form-table">
                    <tr>
                        <th>Enable News</th>
                        <td>
                            <label>
                                <input type="checkbox" name="tfm_plugin_settings[enable_news]" value="1" <?php checked($settings['enable_news'] ?? false, true); ?>>
                                Enable TFM News content type (<code>tfm_news</code>)
                            </label>
                            <p class="description">When enabled, you can create News Items that link outbound to third-party articles in a new tab.</p>
                        </td>
                    </tr>
                </table>

                <?php if (!empty($settings['enable_news'])) : ?>
                    <p>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('edit.php?post_type=tfm_news')); ?>">Manage News Items</a>
                        <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=tfm_news')); ?>">Add News Item</a>
                    </p>
                <?php endif; ?>
            </div>

            <?php submit_button('Save News Settings'); ?>
        </form>
    </div>
    <?php
}

// Render logs page
function tfm_render_logs_page() {
    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    global $tfm_logger;
    $settings = tfm_load_settings();
    
    // Handle log purge action
    if (isset($_POST['purge_logs'])) {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tfm_purge_logs')) {
            wp_die(__('Security check failed. Please try again.'));
        }

        $settings = tfm_load_settings();
        $purged_count = $tfm_logger->purge_logs($settings['log_retention_days']);
        
        if ($purged_count > 0) {
            add_settings_error('tfm_logs', 'logs_purged', sprintf('%d log file(s) have been purged successfully.', $purged_count), 'updated');
        } else {
            add_settings_error('tfm_logs', 'no_logs_purged', 'No log files were old enough to be purged.', 'info');
        }
    }
    
    // Enqueue DataTables
    wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css');
    wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', ['jquery'], null, true);
    wp_enqueue_style(
        'tfm-activity-log',
        TFM_PLUGIN_URL . 'assets/css/activity-log.css',
        [],
        TFM_PLUGIN_VERSION
    );
    
    // Get logs
    $raw_logs = ($tfm_logger instanceof TFM_File_Logger) ? $tfm_logger->get_logs(500) : [];
    usort($raw_logs, function ($a, $b) {
        $timeA = isset($a['timestamp']) ? strtotime($a['timestamp']) : 0;
        $timeB = isset($b['timestamp']) ? strtotime($b['timestamp']) : 0;
        return $timeB <=> $timeA;
    });

    $logs = tfm_prepare_log_rows($raw_logs);
    $log_stats = tfm_collect_log_stats($raw_logs, $tfm_logger);

    $available_actions = [];
    foreach ($logs as $log_entry) {
        $available_actions[$log_entry['action']] = $log_entry['label'];
    }
    ?>
    <div class="wrap">
        <h1>Activity Logs</h1>
        <?php settings_errors('tfm_logs'); ?>
        <?php if (empty($settings['enable_logging'])): ?>
            <div class="notice notice-warning">
                <p><strong>Logging is currently disabled.</strong> Enable activity logging in the main plugin settings to capture new events.</p>
            </div>
        <?php endif; ?>
        
        <div class="tfm-log-wrap">
            <div class="tfm-log-toolbar">
                <div class="tfm-log-card">
                    <h3>Events Loaded</h3>
                    <strong><?php echo esc_html($log_stats['total']); ?></strong>
                    <p>Showing most recent entries (max 500).</p>
                </div>
                <div class="tfm-log-card">
                    <h3>Latest Event</h3>
                    <strong><?php echo esc_html($log_stats['latest']); ?></strong>
                    <p>Retention: <?php echo esc_html($settings['log_retention_days']); ?> days</p>
                </div>
                <div class="tfm-log-card">
                    <h3>Current Log File</h3>
                    <strong><?php echo esc_html($log_stats['file_size']); ?></strong>
                    <?php if (!empty($log_stats['path'])): ?>
                        <p><?php echo esc_html($log_stats['path']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tfm-log-controls">
                <label for="tfm-log-search">Quick search</label>
                <input type="search" id="tfm-log-search" placeholder="Search events, people, IPs">

                <label for="tfm-log-event-filter">Filter by event</label>
                <select id="tfm-log-event-filter">
                    <option value="">All events</option>
                    <?php foreach ($available_actions as $action_key => $action_label): ?>
                        <option value="<?php echo esc_attr($action_key); ?>">
                            <?php echo esc_html($action_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <form method="post" style="margin-left:auto;">
                    <?php wp_nonce_field('tfm_purge_logs'); ?>
                    <input type="submit" name="purge_logs" class="button button-secondary" value="Purge Old Logs" onclick="return confirm('Are you sure you want to purge old logs?');">
                </form>
            </div>

            <?php if (!empty($logs)): ?>
                <table id="tfm-activity-logs" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Event</th>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Source</th>
                            <th>Details</th>
                            <th class="tfm-log-col-hidden">Action Key</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr data-action="<?php echo esc_attr($log['action']); ?>">
                                <td>
                                    <span class="tfm-log-badge tfm-log-badge--<?php echo esc_attr($log['severity']); ?>">
                                        <?php echo esc_html(ucfirst($log['severity'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="tfm-log-event">
                                        <span class="dashicons <?php echo esc_attr($log['icon']); ?>"></span>
                                        <div>
                                            <strong><?php echo esc_html($log['label']); ?></strong>
                                            <?php if (!empty($log['message'])): ?>
                                                <p><?php echo esc_html($log['message']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($log['context_badges'])): ?>
                                                <div class="tfm-log-context">
                                                    <?php foreach ($log['context_badges'] as $badge): ?>
                                                        <span class="tfm-log-chip">
                                                            <strong><?php echo esc_html($badge['label']); ?>:</strong>
                                                            <?php echo esc_html($badge['value']); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td data-order="<?php echo esc_attr($log['timestamp_order']); ?>">
                                    <div class="tfm-log-time">
                                        <strong><?php echo esc_html($log['timestamp_human']); ?></strong>
                                        <?php if (!empty($log['timestamp_relative'])): ?>
                                            <span><?php echo esc_html($log['timestamp_relative']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($log['user_display']); ?></strong>
                                    <?php if (!empty($log['user_meta'])): ?>
                                        <br><span><?php echo esc_html($log['user_meta']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($log['source']); ?></td>
                                <td>
                                    <button type="button" class="button-link tfm-log-details-toggle" data-target="<?php echo esc_attr($log['id']); ?>" aria-expanded="false">
                                        View details
                                    </button>
                                    <div id="<?php echo esc_attr($log['id']); ?>" class="tfm-log-details-panel" hidden>
                                        <?php echo wp_kses_post($log['details_html']); ?>
                                    </div>
                                </td>
                                <td class="tfm-log-col-hidden"><?php echo esc_html($log['action']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="tfm-log-empty">
                    <h2>No events found</h2>
                    <p>Once activity logging captures new events, they will appear here with human-readable summaries.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($logs)): ?>
        <script>
        jQuery(document).ready(function($) {
            var table = $('#tfm-activity-logs').DataTable({
                order: [[2, 'desc']],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                columnDefs: [
                    { targets: [5], orderable: false },
                    { targets: [6], visible: false, searchable: true }
                ]
            });

            $('#tfm-log-search').on('keyup', function() {
                table.search(this.value).draw();
            });

            $('#tfm-log-event-filter').on('change', function() {
                table.column(6).search(this.value).draw();
            });

            $(document).on('click', '.tfm-log-details-toggle', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                var $panel = $('#' + targetId);
                var isHidden = $panel.prop('hidden');
                $panel.prop('hidden', !isHidden);
                $(this)
                    .attr('aria-expanded', isHidden ? 'true' : 'false')
                    .text(isHidden ? 'Hide details' : 'View details');
            });
        });
        </script>
        <?php endif; ?>
    </div>
    <?php
}

// Register settings
function tfm_register_settings() {
    register_setting('tfm_plugin_settings_group', 'tfm_plugin_settings', [
        'sanitize_callback' => 'tfm_sanitize_settings'
    ]);
}
add_action('admin_init', 'tfm_register_settings');

// Enqueue media on our settings page only
function tfm_admin_enqueue_media($hook) {
    if ($hook !== 'toplevel_page_tfm-custom-functions') return;
    if (!current_user_can('manage_options')) return;
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'tfm_admin_enqueue_media');

// Sanitize settings
function tfm_sanitize_settings($input) {
    $sanitized = [];

    // The News Settings screen only posts enable_news; preserve everything else.
    if (($input['_settings_context'] ?? '') === 'news_only') {
        $existing_settings = tfm_load_settings();
        $existing_settings['enable_news'] = isset($input['enable_news']);
        return $existing_settings;
    }
    
    // Sanitize boolean values
    $sanitized['enable_svg_uploads'] = isset($input['enable_svg_uploads']);
    $sanitized['enable_shortcodes'] = isset($input['enable_shortcodes']);
    $sanitized['enable_logging'] = isset($input['enable_logging']);
    $sanitized['enable_userway'] = isset($input['enable_userway']);
    $sanitized['enable_news'] = isset($input['enable_news']);
    $sanitized['enable_whatconverts'] = isset($input['enable_whatconverts']);
    $sanitized['defer_scripts'] = isset($input['defer_scripts']);
    $sanitized['defer_handles'] = sanitize_text_field($input['defer_handles'] ?? '');
    
    // Sanitize video defer settings
    $sanitized['video_defer'] = [
        'enabled' => isset($input['video_defer']['enabled']),
        'elementor_enabled' => isset($input['video_defer']['elementor_enabled']),
        'divi_enabled' => isset($input['video_defer']['divi_enabled'])
    ];
    
    // Sanitize text fields
    $phone_input = sanitize_text_field($input['phone'] ?? '');
    $phone_digits = preg_replace('/\D/', '', $phone_input);
    
    // Validate phone number - must have at least 10 numeric characters
    // Only validate if phone is being changed (not empty) to avoid breaking existing sites
    if (!empty($phone_input) && strlen($phone_digits) < 10) {
        add_settings_error(
            'tfm_plugin_settings',
            'phone_invalid',
            'Phone number must contain at least 10 numeric digits.',
            'error'
        );
        // Keep the existing value if validation fails
        $existing_settings = get_option('tfm_plugin_settings', []);
        $sanitized['phone'] = $existing_settings['phone'] ?? '';
    } else {
        // Allow empty phone (for backward compatibility) or valid phone
        $sanitized['phone'] = $phone_input;
    }
    
    // Sanitize phone format (default to '4' for backward compatibility)
    $valid_formats = ['1', '2', '3', '4'];
    $sanitized['phone_format'] = in_array($input['phone_format'] ?? '4', $valid_formats) ? $input['phone_format'] : '4';
    
    $sanitized['email'] = sanitize_email($input['email'] ?? '');
    $sanitized['userway_account_id'] = sanitize_text_field($input['userway_account_id'] ?? '');
    $sanitized['whatconverts_account_id'] = sanitize_text_field($input['whatconverts_account_id'] ?? '');
    $sanitized['login_logo_url'] = esc_url_raw($input['login_logo_url'] ?? '');
    
    // Sanitize color
    $sanitized['userway_color'] = sanitize_hex_color($input['userway_color'] ?? '#003D71');
    
    // Sanitize position values
    $valid_positions = ['top_right', 'middle_right', 'bottom_right', 'bottom_middle', 'bottom_left', 'middle_left', 'top_left', 'top_middle'];
    $sanitized['userway_position'] = in_array($input['userway_position'] ?? '', $valid_positions) ? $input['userway_position'] : 'bottom_right';
    
    $valid_mobile_positions = array_merge(['default'], $valid_positions);
    $sanitized['userway_mobile_position'] = in_array($input['userway_mobile_position'] ?? '', $valid_mobile_positions) ? $input['userway_mobile_position'] : 'default';

    // Sanitize accessiBe settings
    $sanitized['enable_accessibe']       = isset($input['enable_accessibe']);
    $sanitized['accessibe_hide_mobile']  = isset($input['accessibe_hide_mobile']);
    $sanitized['accessibe_color']        = sanitize_hex_color($input['accessibe_color'] ?? '#146FF8') ?? '#146FF8';
    $sanitized['accessibe_statement_link'] = esc_url_raw($input['accessibe_statement_link'] ?? '');
    $valid_langs = ['en','es','fr','de','it','pt','nl','da','sv','no','fi','pl','ru','ar','he','ja','zh'];
    $sanitized['accessibe_language']    = in_array($input['accessibe_language'] ?? 'en', $valid_langs) ? $input['accessibe_language'] : 'en';
    $sanitized['accessibe_position_x']  = in_array($input['accessibe_position_x'] ?? 'right', ['left','right']) ? $input['accessibe_position_x'] : 'right';
    $sanitized['accessibe_position_y']  = in_array($input['accessibe_position_y'] ?? 'bottom', ['top','center','bottom']) ? $input['accessibe_position_y'] : 'bottom';
    $valid_icons = ['checkmark','display','display2','display3','help','people','people2','settings','settings2','wheels','wheels2'];
    $sanitized['accessibe_trigger_icon']  = in_array($input['accessibe_trigger_icon'] ?? 'people', $valid_icons) ? $input['accessibe_trigger_icon'] : 'people';
    $sanitized['accessibe_trigger_size']  = in_array($input['accessibe_trigger_size'] ?? 'medium', ['small','medium','big']) ? $input['accessibe_trigger_size'] : 'medium';
    $sanitized['accessibe_trigger_shape'] = in_array($input['accessibe_trigger_shape'] ?? 'round', ['round','square']) ? $input['accessibe_trigger_shape'] : 'round';

    // Sanitize numeric values
    $sanitized['log_retention_days'] = absint($input['log_retention_days'] ?? 30);
    if ($sanitized['log_retention_days'] < 1) $sanitized['log_retention_days'] = 1;
    if ($sanitized['log_retention_days'] > 365) $sanitized['log_retention_days'] = 365;

    $log_level = $input['log_level'] ?? 'all';
    $sanitized['log_level'] = in_array($log_level, ['all', 'important', 'critical'], true) ? $log_level : 'all';
    
    // Sanitize scripts — admin-only fields; preserve raw code exactly as entered
    $sanitized['custom_head_scripts'] = wp_unslash($input['custom_head_scripts'] ?? '');
    $sanitized['custom_footer_scripts'] = wp_unslash($input['custom_footer_scripts'] ?? '');
    
    // Sanitize Lead Magnet
    $sanitized['lead_magnet'] = [
        'image_id' => isset($input['lead_magnet']['image_id']) ? absint($input['lead_magnet']['image_id']) : 0,
        'file_id' => isset($input['lead_magnet']['file_id']) ? absint($input['lead_magnet']['file_id']) : 0,
    ];
    
    // Sanitize Franchisee Financials
    $sanitized['franchisee_financials'] = [
        'estimated_initial_investment' => sanitize_text_field($input['franchisee_financials']['estimated_initial_investment'] ?? ''),
        'minimum_liquid_capital' => sanitize_text_field($input['franchisee_financials']['minimum_liquid_capital'] ?? ''),
        'franchise_fee' => sanitize_text_field($input['franchisee_financials']['franchise_fee'] ?? ''),
        'net_worth' => sanitize_text_field($input['franchisee_financials']['net_worth'] ?? ''),
        'average_unit_volume' => sanitize_text_field($input['franchisee_financials']['average_unit_volume'] ?? ''),
    ];
    
    // Sanitize Full Address
    $sanitized['full_address'] = wp_kses_post($input['full_address'] ?? '');

    // Sanitize HTML Sitemap settings
    $sanitized['sitemap_enabled'] = isset($input['sitemap_enabled']);
    $sanitized['sitemap_show_dates'] = isset($input['sitemap_show_dates']);
    $sanitized['sitemap_show_counts'] = isset($input['sitemap_show_counts']);
    $sanitized['sitemap_exclude_empty_cats'] = isset($input['sitemap_exclude_empty_cats']);

    // Sanitize post types array
    $sanitized['sitemap_post_types'] = [];
    if (isset($input['sitemap_post_types']) && is_array($input['sitemap_post_types'])) {
        $valid_post_types = get_post_types(['public' => true], 'names');
        foreach ($input['sitemap_post_types'] as $post_type) {
            if (in_array($post_type, $valid_post_types)) {
                $sanitized['sitemap_post_types'][] = sanitize_key($post_type);
            }
        }
    }
    // Default to page and post if none selected
    if (empty($sanitized['sitemap_post_types'])) {
        $sanitized['sitemap_post_types'] = ['page', 'post'];
    }

    // Sanitize cache timeout
    $cache_timeout = absint($input['sitemap_cache_timeout'] ?? 3600);
    $sanitized['sitemap_cache_timeout'] = max(300, min(86400, $cache_timeout)); // Between 5 minutes and 24 hours

    // Clear sitemap cache when settings change
    if (function_exists('tfm_sitemap_clear_cache')) {
        tfm_sitemap_clear_cache();
    }

    // Sanitize Debloat and Debug settings
    $sanitized['disable_wp_revisions'] = isset($input['disable_wp_revisions']);
    $revisions_limit = absint($input['wp_post_revisions_limit'] ?? 5);
    $sanitized['wp_post_revisions_limit'] = max(0, $revisions_limit); // Cannot be negative

    $sanitized['disable_emojis'] = isset($input['disable_emojis']);
    $sanitized['disable_jquery_migrate'] = isset($input['disable_jquery_migrate']);
    $sanitized['disable_oembeds'] = isset($input['disable_oembeds']);

    return $sanitized;
}

// Debug page for sitemap testing
function tfm_render_sitemap_debug_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Clear cache on page load to ensure fresh content
    tfm_sitemap_clear_cache();

    $settings = tfm_load_settings();
    ?>
    <div class="wrap">
        <h1>TFM HTML Sitemap Debug</h1>

        <div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #007cba;">
            <h2>Current Settings</h2>
            <pre style="background: white; padding: 10px; overflow: auto;"><?php echo esc_html(print_r($settings, true)); ?></pre>
        </div>

        <div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;">
            <h2>Function Tests</h2>
            <p><strong>tfm_sitemap_is_enabled():</strong> <?php echo tfm_sitemap_is_enabled() ? '<span style="color: green;">TRUE</span>' : '<span style="color: red;">FALSE</span>'; ?></p>
            <p><strong>Shortcode registered:</strong> <?php echo isset($GLOBALS['shortcode_tags']['tfm_sitemap']) ? '<span style="color: green;">YES</span>' : '<span style="color: red;">NO</span>'; ?></p>
        </div>

        <div style="background: #f8f9fa; padding: 20px; margin: 20px 0; border: 1px solid #dee2e6;">
            <h2>Test Sitemap Generation</h2>
            <p><strong>Basic sitemap:</strong></p>
            <div style="background: white; padding: 15px; border: 1px solid #ccc; max-height: 400px; overflow: auto;">
                <?php echo tfm_sitemap_generate(); ?>
            </div>

            <?php if (tfm_sitemap_is_enabled()): ?>
                <p><strong>With custom parameters:</strong></p>
                <div style="background: white; padding: 15px; border: 1px solid #ccc; max-height: 200px; overflow: auto;">
                    <?php echo tfm_sitemap_generate(['post_types' => 'page', 'show_dates' => 'false']); ?>
                </div>
            <?php else: ?>
                <p><em>Sitemap is disabled - enable it in settings to test generation.</em></p>
            <?php endif; ?>
        </div>

        <div style="background: #e7f5ff; padding: 20px; margin: 20px 0; border-left: 4px solid #0066cc;">
            <h2>Debug Instructions</h2>
            <ol>
                <li>Enable the HTML Sitemap feature in the main settings</li>
                <li>Save the settings</li>
                <li>Refresh this debug page</li>
                <li>Check the debug.log file for detailed logging</li>
                <li>Test the sitemap generation above</li>
            </ol>

            <h3>Cache Management</h3>
            <p>If you're still seeing old output, the sitemap may be cached. Click below to clear the cache:</p>
            <form method="post" action="">
                <?php wp_nonce_field('tfm_clear_sitemap_cache', 'tfm_cache_nonce'); ?>
                <input type="hidden" name="tfm_clear_cache" value="1">
                <button type="submit" class="button button-primary">Clear Sitemap Cache</button>
            </form>

            <?php
            if (isset($_POST['tfm_clear_cache']) && isset($_POST['tfm_cache_nonce']) && wp_verify_nonce($_POST['tfm_cache_nonce'], 'tfm_clear_sitemap_cache')) {
                tfm_sitemap_clear_cache();
                echo '<div class="notice notice-success is-dismissible"><p>Sitemap cache cleared successfully!</p></div>';
            }
            ?>
        </div>
    </div>
    <?php
}

// Login logo customization (optional via setting)
function wpb_login_logo() {
    $settings = tfm_load_settings();
    $logo_url = isset($settings['login_logo_url']) ? esc_url($settings['login_logo_url']) : '';
    if (empty($logo_url)) return; ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url(<?php echo $logo_url; ?>);
            height: 80px;
            width: 80px;
            background-size: 80px 80px;
            background-repeat: no-repeat;
            padding-bottom: 10px;
        }
    </style>
<?php }
add_action('login_enqueue_scripts', 'wpb_login_logo');

function my_login_logo_url() {
    return home_url();
}
add_filter('login_headerurl', 'my_login_logo_url');

function my_login_logo_url_title() {
    return 'Your Site Name and Info';
}
add_filter('login_headertext', 'my_login_logo_url_title');

// Prevent class redeclaration
if (!class_exists('TFM_Plugin')) {
    class TFM_Plugin {
        // ... rest of the class code ...
    }
}
