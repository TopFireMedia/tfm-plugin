<?php
/**
 * TFM Dev-Site Noindex Guard
 *
 * Development sites — any subdomain of tfmstaging.com (and the apex) — must never
 * be indexed by search engines. This forces WordPress's "Discourage search
 * engines" setting (blog_public = 0) on those sites and keeps it that way: it
 * can't be re-enabled from Settings → Reading. Live sites are untouched.
 *
 * Detection is filterable via 'tfm_is_dev_site' so other dev domains can be added
 * later without editing this file.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Is the current site a development site? True for tfmstaging.com and any of its
 * subdomains. Result is cached per request; filterable.
 *
 * @return bool
 */
function tfm_is_dev_site() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $host   = function_exists('home_url') ? wp_parse_url(home_url(), PHP_URL_HOST) : '';
    $is_dev = $host && preg_match('/(^|\.)tfmstaging\.com$/i', $host);
    $cache  = (bool) apply_filters('tfm_is_dev_site', $is_dev, $host);
    return $cache;
}

/**
 * Force blog_public to 0 (noindex) on dev sites at read time. Because this
 * short-circuits get_option(), WordPress behaves as noindex regardless of the
 * stored value, and toggling Settings → Reading has no effect.
 */
function tfm_force_dev_noindex($pre) {
    return tfm_is_dev_site() ? '0' : $pre;
}
add_filter('pre_option_blog_public', 'tfm_force_dev_noindex');

/**
 * Keep the STORED value at 0 too, so Plesk, the settings screen, and the fleet
 * heartbeat all agree. The read filter is briefly lifted so update_option()
 * actually writes (it compares against the current value, which the filter would
 * otherwise force to 0). Runs on admin_init — cheap, and only acts on dev sites.
 */
function tfm_normalize_dev_blog_public() {
    if (!tfm_is_dev_site()) {
        return;
    }
    remove_filter('pre_option_blog_public', 'tfm_force_dev_noindex');
    $current = get_option('blog_public');
    if ($current !== '0' && $current !== 0) {
        update_option('blog_public', 0);
    }
    add_filter('pre_option_blog_public', 'tfm_force_dev_noindex');
}
add_action('admin_init', 'tfm_normalize_dev_blog_public');

/**
 * On the Reading settings screen of a dev site, explain that indexing is locked
 * off — so nobody wonders why the checkbox won't stick.
 */
function tfm_dev_noindex_notice() {
    if (!tfm_is_dev_site() || !function_exists('get_current_screen')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'options-reading') {
        return;
    }
    echo '<div class="notice notice-info"><p><strong>TFM Custom Functions:</strong> This is a development site (tfmstaging.com), so search-engine indexing is locked <strong>off</strong> and can\'t be enabled here.</p></div>';
}
add_action('admin_notices', 'tfm_dev_noindex_notice');
