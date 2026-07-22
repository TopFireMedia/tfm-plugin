<?php
/**
 * TFM Front-end Optimizations
 * jQuery Migrate / oEmbed / post-revision tweaks. Moved verbatim from
 * topfiremedia.php during modularization.
 */

if (!defined('ABSPATH')) {
    exit;
}

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

// Disable WordPress emojis when the setting is enabled (restored from a
// previously commented-out one-line blob — the feature was a no-op before).
function tfm_disable_emojis() {
    $settings = tfm_load_settings();
    if (empty($settings['disable_emojis'])) {
        return;
    }
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', 'tfm_disable_emojis_tinymce');
    add_filter('wp_resource_hints', 'tfm_disable_emojis_remove_dns_prefetch', 10, 2);
}
add_action('init', 'tfm_disable_emojis');

function tfm_disable_emojis_tinymce($plugins) {
    return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : $plugins;
}

function tfm_disable_emojis_remove_dns_prefetch($urls, $relation_type) {
    if ('dns-prefetch' === $relation_type) {
        foreach ($urls as $key => $url) {
            if (preg_match('#//s\.w\.org/images/core/emoji/#i', is_string($url) ? $url : ($url['href'] ?? ''))) {
                unset($urls[$key]);
            }
        }
    }
    return $urls;
}
