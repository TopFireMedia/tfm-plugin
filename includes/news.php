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

/*
 * Expose the outbound settings to the REST API.
 *
 * The metabox above writes these on save_post, which covers wp-admin but leaves
 * them unreachable over REST: a tfm_news item created through the API comes back
 * with meta null and no link, which is the one thing this post type exists for.
 * Both keys are protected (leading underscore), so WordPress refuses REST writes
 * unless an explicit auth_callback grants them - the default denies all.
 */
function tfm_news_register_meta() {
    if (!tfm_news_is_enabled()) {
        return;
    }

    $auth = function ($allowed, $meta_key, $post_id) {
        return current_user_can('edit_post', $post_id);
    };

    register_post_meta('tfm_news', '_tfm_news_outbound_url', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'esc_url_raw',
        'auth_callback'     => $auth,
    ]);

    register_post_meta('tfm_news', '_tfm_news_source_name', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => $auth,
    ]);
}
add_action('init', 'tfm_news_register_meta');

/*
 * Expose the two outbound fields as explicit REST fields.
 *
 * register_post_meta above is not enough on its own: WP_REST_Posts_Controller
 * only attaches the `meta` object for post types that support 'custom-fields',
 * and tfm_news deliberately does not - adding it would switch on the raw Custom
 * Fields panel in the editor, which is noise for the people writing news items.
 * Without that support the meta writes are dropped silently, which is how a
 * scripted item ends up published with no link.
 *
 * register_rest_field sidesteps the supports check and gives the fields clearer
 * names than the underscore-prefixed meta keys.
 */
function tfm_news_register_rest_fields() {
    if (!tfm_news_is_enabled()) {
        return;
    }

    $fields = [
        'tfm_news_outbound_url' => ['_tfm_news_outbound_url', 'esc_url_raw'],
        'tfm_news_source_name'  => ['_tfm_news_source_name', 'sanitize_text_field'],
    ];

    foreach ($fields as $field => $spec) {
        list($meta_key, $sanitizer) = $spec;

        register_rest_field('tfm_news', $field, [
            'get_callback' => function ($post) use ($meta_key) {
                return (string) get_post_meta($post['id'], $meta_key, true);
            },
            'update_callback' => function ($value, $post) use ($meta_key, $sanitizer) {
                if (!current_user_can('edit_post', $post->ID)) {
                    return new WP_Error(
                        'rest_cannot_edit',
                        __('Sorry, you are not allowed to edit this news item.', 'topfiremedia'),
                        ['status' => 403]
                    );
                }

                $value = call_user_func($sanitizer, (string) $value);

                if ($value === '') {
                    delete_post_meta($post->ID, $meta_key);
                } else {
                    update_post_meta($post->ID, $meta_key, $value);
                }

                return true;
            },
            'schema' => [
                'type'        => 'string',
                'context'     => ['view', 'edit'],
                'description' => __('Outbound article settings for this news item.', 'topfiremedia'),
            ],
        ]);
    }
}
add_action('rest_api_init', 'tfm_news_register_rest_fields');
