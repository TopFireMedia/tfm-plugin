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
