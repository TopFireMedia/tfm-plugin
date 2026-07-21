<?php
/**
 * TFM Settings Loader — tfm_load_settings() and its per-request cache
 * Moved verbatim from topfiremedia.php during modularization — no logic change.
 */

if (!defined('ABSPATH')) {
    exit;
}

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
            'enable_font_awesome' => true,
            'enable_phone_formatter' => true,
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
