<?php
/**
 * Plugin Name: TFM Custom Functions
 * Plugin URI: https://topfiremedia.com
 * Description: A comprehensive plugin for TFM functionality including logging, video optimization, and more.
 * Version: 3.19.0
 * Author: TopFireMedia
 * Author URI: https://topfiremedia.com
 * Text Domain: topfiremedia
 * Domain Path: /languages

 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('TFM_PLUGIN_VERSION', '3.19.0');
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

// Include required files — settings loader first (modules read settings at load time).
require_once TFM_PLUGIN_DIR . 'includes/settings.php';

require_once TFM_PLUGIN_DIR . 'includes/class-tfm-activation-checks.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-file-logger.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-logging-hooks.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-updater.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-video-defer.php';
require_once TFM_PLUGIN_DIR . 'includes/class-tfm-svg-sanitizer.php';

// Modularized feature includes (moved verbatim from this file).
require_once TFM_PLUGIN_DIR . 'includes/login-branding.php';
require_once TFM_PLUGIN_DIR . 'includes/svg-uploads.php';
require_once TFM_PLUGIN_DIR . 'includes/upgrades.php';
require_once TFM_PLUGIN_DIR . 'includes/optimizations.php';
require_once TFM_PLUGIN_DIR . 'includes/sitemap.php';
require_once TFM_PLUGIN_DIR . 'includes/revisions.php';
require_once TFM_PLUGIN_DIR . 'includes/news.php';
require_once TFM_PLUGIN_DIR . 'includes/frontend-scripts.php';
require_once TFM_PLUGIN_DIR . 'includes/admin.php';
require_once TFM_PLUGIN_DIR . 'includes/shortcodes.php';

// Absorbed team plugins.
require_once TFM_PLUGIN_DIR . 'includes/press-releases.php';
require_once TFM_PLUGIN_DIR . 'includes/cookie-consent.php';

// Fleet alerting (critical activity-log events -> ClickUp via the TFM relay).
require_once TFM_PLUGIN_DIR . 'includes/clickup-alerts.php';
require_once TFM_PLUGIN_DIR . 'includes/heartbeat.php';

// Background auto-updates for the plugin itself (fleet stays current, no per-site action).
require_once TFM_PLUGIN_DIR . 'includes/auto-update.php';


// Activation hook
register_activation_hook(__FILE__, 'tfm_activate_plugin');



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

// Action logging function
function tfm_log_action($action, $data = []) {
    global $tfm_logger;
    $settings = tfm_load_settings();

    if (isset($settings['enable_logging']) && $settings['enable_logging'] && $tfm_logger) {
        $tfm_logger->log_action($action, $data);
    }
}


// Enqueue scripts conditionally



// Insert custom scripts from settings

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




