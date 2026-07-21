<?php
/**
 * TFM News — tfm_news custom post type + metaboxes
 * Moved verbatim from topfiremedia.php during modularization — no logic change.
 */

if (!defined('ABSPATH')) {
    exit;
}

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
