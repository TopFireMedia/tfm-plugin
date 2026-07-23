<?php
/**
 * TFM Press Releases
 *
 * Registers the `press_release` custom post type, its ACF/SCF field group, and
 * the "Press Release Grid" Elementor widget.
 *
 * Absorbed from the standalone "Press Release Manager" plugin. The data contract
 * is preserved exactly — the CPT name (`press_release`), the field names
 * (external_url / source_name / release_date / featured_release), the field
 * group key (group_prm_fields), and the widget name (press_release_grid) — so
 * existing press releases and Elementor pages continue to work unchanged.
 *
 * Requires ACF or Secure Custom Fields for the field group (fields register only
 * when acf_add_local_field_group() is available; the CPT registers regardless).
 */

if (!defined('ABSPATH')) {
    exit;
}

// If the standalone "Press Release Manager" is still active, stay dormant this
// request and deactivate it so TFM takes over cleanly on the next load (avoids
// a fatal class/CPT redeclaration during the overlap).
if (tfm_handover_absorbed_plugin(
    array('press-release-manager.php', 'press-release-manager', 'Press Release Manager'),
    array('Press Release Manager'),
    'prm'
)) {
    return;
}

/**
 * Register the press_release custom post type.
 */
function tfm_press_release_register_cpt() {
    $labels = array(
        'name'               => 'Press Releases',
        'singular_name'      => 'Press Release',
        'menu_name'          => 'Press Releases',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Press Release',
        'edit_item'          => 'Edit Press Release',
        'new_item'           => 'New Press Release',
        'view_item'          => 'View Press Release',
        'search_items'       => 'Search Press Releases',
        'not_found'          => 'No press releases found',
        'not_found_in_trash' => 'No press releases found in Trash',
    );

    register_post_type('press_release', array(
        'labels'       => $labels,
        'public'       => true,
        'has_archive'  => true,
        'menu_icon'    => 'dashicons-media-document',
        'supports'     => array('title', 'editor', 'thumbnail', 'excerpt', 'author'),
        'show_in_rest' => true,
        'taxonomies'   => array(),
    ));
}
add_action('init', 'tfm_press_release_register_cpt');

/**
 * Register the Press Release ACF/SCF field group (only when ACF/SCF is active).
 */
function tfm_press_release_register_fields() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group(array(
        'key'      => 'group_prm_fields',
        'title'    => 'Press Release Details',
        'fields'   => array(
            array(
                'key'      => 'field_prm_external_url',
                'label'    => 'External Article URL',
                'name'     => 'external_url',
                'type'     => 'url',
                'required' => 1,
            ),
            array(
                'key'          => 'field_prm_source_name',
                'label'        => 'Source Name',
                'name'         => 'source_name',
                'type'         => 'text',
                'instructions' => 'Enter the source/publication name for this press release',
            ),
            array(
                'key'            => 'field_prm_release_date',
                'label'          => 'Release Date',
                'name'           => 'release_date',
                'type'           => 'date_picker',
                'display_format' => 'd/m/Y',
                'return_format'  => 'Y-m-d',
            ),
            array(
                'key'           => 'field_prm_featured',
                'label'         => 'Featured Release',
                'name'          => 'featured_release',
                'type'          => 'true_false',
                'default_value' => 0,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'press_release',
                ),
            ),
        ),
    ));
}
add_action('init', 'tfm_press_release_register_fields', 20);

/**
 * Native meta box for the Press Release fields — the SCF-free fallback.
 *
 * Registered ONLY when ACF/SCF is not available, so press releases stay fully
 * editable without the Secure Custom Fields dependency. When ACF/SCF IS active,
 * the local field group above provides the UI instead (no duplicate box). Either
 * way the values are stored as ordinary post meta under the same keys
 * (external_url / source_name / release_date / featured_release) and in ACF's
 * storage format (dates as Ymd), so existing data and the Elementor widget work
 * unchanged and SCF can be added or removed at any time.
 */
function tfm_press_release_register_meta_box() {
    if (function_exists('acf_add_local_field_group')) {
        return; // ACF/SCF present — its field group renders the fields
    }
    add_meta_box(
        'tfm_prm_details',
        __('Press Release Details', 'topfiremedia'),
        'tfm_press_release_render_meta_box',
        'press_release',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'tfm_press_release_register_meta_box');

function tfm_press_release_render_meta_box($post) {
    wp_nonce_field('tfm_prm_save_meta', 'tfm_prm_meta_nonce');

    $external_url = get_post_meta($post->ID, 'external_url', true);
    $source_name  = get_post_meta($post->ID, 'source_name', true);
    $featured     = get_post_meta($post->ID, 'featured_release', true);

    // Stored as Ymd (ACF format); present it to the native date input as Y-m-d.
    $release_raw = get_post_meta($post->ID, 'release_date', true);
    $release_val = '';
    if ($release_raw) {
        $d = DateTime::createFromFormat('Ymd', $release_raw);
        if (!$d) {
            $d = DateTime::createFromFormat('Y-m-d', $release_raw);
        }
        if ($d) {
            $release_val = $d->format('Y-m-d');
        }
    }
    ?>
    <style>
        .tfm-prm-field{margin:14px 0}
        .tfm-prm-field label.tfm-prm-label{display:block;font-weight:600;margin-bottom:4px}
        .tfm-prm-field input[type=url],.tfm-prm-field input[type=text]{width:100%;max-width:540px}
    </style>
    <div class="tfm-prm-field">
        <label class="tfm-prm-label" for="tfm_prm_external_url">External Article URL <span style="color:#d63638">*</span></label>
        <input type="url" id="tfm_prm_external_url" name="tfm_prm_external_url" value="<?php echo esc_attr($external_url); ?>" placeholder="https://example.com/article">
    </div>
    <div class="tfm-prm-field">
        <label class="tfm-prm-label" for="tfm_prm_source_name">Source Name</label>
        <input type="text" id="tfm_prm_source_name" name="tfm_prm_source_name" value="<?php echo esc_attr($source_name); ?>">
    </div>
    <div class="tfm-prm-field">
        <label class="tfm-prm-label" for="tfm_prm_release_date">Release Date</label>
        <input type="date" id="tfm_prm_release_date" name="tfm_prm_release_date" value="<?php echo esc_attr($release_val); ?>">
    </div>
    <div class="tfm-prm-field">
        <label><input type="checkbox" name="tfm_prm_featured" value="1" <?php checked($featured, '1'); ?>> Featured Release</label>
    </div>
    <?php
}

/**
 * Save the native Press Release meta box. Guarded by its own nonce, so it only
 * runs when that box was actually rendered (i.e. on SCF-free sites); when ACF/SCF
 * handles the fields this no-ops. Stores the same keys/format ACF uses.
 */
function tfm_press_release_save_meta_box($post_id) {
    if (!isset($_POST['tfm_prm_meta_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfm_prm_meta_nonce'])), 'tfm_prm_save_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    update_post_meta($post_id, 'external_url', esc_url_raw(wp_unslash($_POST['tfm_prm_external_url'] ?? '')));
    update_post_meta($post_id, 'source_name', sanitize_text_field(wp_unslash($_POST['tfm_prm_source_name'] ?? '')));

    // Convert the date input (Y-m-d) to ACF's storage format (Ymd) so ordering
    // and existing data stay consistent.
    $date_in = sanitize_text_field(wp_unslash($_POST['tfm_prm_release_date'] ?? ''));
    $date_stored = '';
    if ($date_in) {
        $d = DateTime::createFromFormat('Y-m-d', $date_in);
        if ($d) {
            $date_stored = $d->format('Ymd');
        }
    }
    update_post_meta($post_id, 'release_date', $date_stored);

    update_post_meta($post_id, 'featured_release', isset($_POST['tfm_prm_featured']) ? '1' : '0');
}
add_action('save_post_press_release', 'tfm_press_release_save_meta_box');

/**
 * Register the "Press Release Grid" Elementor widget.
 */
function tfm_press_release_register_widget($widgets_manager) {
    if (!did_action('elementor/loaded')) {
        return;
    }

    $widget_file = TFM_PLUGIN_DIR . 'includes/elementor-widgets/widget-press-release.php';
    if (file_exists($widget_file)) {
        require_once $widget_file;
        if (class_exists('Elementor_PRM_Widget')) {
            $widgets_manager->register(new \Elementor_PRM_Widget());
        }
    }
}
add_action('elementor/widgets/register', 'tfm_press_release_register_widget');
