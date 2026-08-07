<?php
/**
 * Elementor Forms — report acceptance fields as Yes/No.
 *
 * An unchecked HTML checkbox submits nothing, so Elementor has no value to
 * render: the field appears in the notification email with its label and a blank
 * value, and `[field id="..."]` resolves to an empty string. That makes an
 * unchecked opt-in indistinguishable from a broken form, which matters when the
 * checkbox records consent.
 *
 * Elementor's Form_Record::set_fields() seeds every declared field with an empty
 * value, so an unchecked acceptance field IS present in the record — it just has
 * nothing in it. That means we can fill it in rather than having to add it.
 *
 * We hook `elementor_pro/forms/process`, which fires at the end of
 * process_fields() and therefore before any action (email, webhook, save to
 * database) reads the record. Elementor's own Upload field uses the same hook to
 * populate values, so this is the supported place to do it.
 *
 * Affects every submit action, not just email: the database entry and any
 * webhook payload get the explicit value too.
 *
 * OFF by default. This changes the wording of client notification emails, so it
 * is opted into per site rather than applied fleet-wide. Turn it on at
 * TFM Custom Functions → General → "Form Acceptance Yes/No", or with:
 *
 *   wp option update tfm_form_acceptance_enabled 1
 *
 * Change the words:  add_filter( 'tfm_form_acceptance_labels', function ( $l ) {
 *                        return array( 'checked' => 'Opted in', 'unchecked' => 'Declined' );
 *                    } );
 * Force on/off in code: add_filter( 'tfm_form_acceptance_values', '__return_true' );
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('elementor_pro/forms/process', 'tfm_form_acceptance_values', 10, 2);

/**
 * Give every acceptance field an explicit value before the form's actions run.
 *
 * @param object $record       Elementor Pro Form_Record.
 * @param object $ajax_handler Elementor Pro Ajax_Handler (unused).
 */
function tfm_form_acceptance_values($record, $ajax_handler) {
    // Opt-in per site: only the sites whose client wants this get it. The admin
    // checkbox is the normal route; the standalone option is kept as an
    // equivalent so it can be flipped by wp-cli without loading the settings UI.
    $settings = function_exists('tfm_load_settings')
        ? tfm_load_settings()
        : get_option('tfm_plugin_settings', array());
    $enabled = !empty($settings['elementor_acceptance_values'])
        || (bool) get_option('tfm_form_acceptance_enabled', false);

    if (!apply_filters('tfm_form_acceptance_values', $enabled, $record)) {
        return;
    }
    if (!is_object($record) || !method_exists($record, 'get') || !method_exists($record, 'update_field')) {
        return;
    }

    $fields = $record->get('fields');
    if (!is_array($fields)) {
        return;
    }

    $labels = apply_filters('tfm_form_acceptance_labels', array(
        'checked'   => __('Yes', 'topfiremedia'),
        'unchecked' => __('No', 'topfiremedia'),
    ));
    $checked_label   = isset($labels['checked']) ? $labels['checked'] : 'Yes';
    $unchecked_label = isset($labels['unchecked']) ? $labels['unchecked'] : 'No';

    foreach ($fields as $id => $field) {
        if (!is_array($field) || !isset($field['type']) || 'acceptance' !== $field['type']) {
            continue;
        }

        // Elementor stores the checkbox's submitted value in raw_value; an
        // unchecked box leaves both raw_value and value as ''. Anything
        // non-empty means the visitor ticked it.
        $raw = '';
        if (isset($field['raw_value'])) {
            $raw = $field['raw_value'];
        } elseif (isset($field['value'])) {
            $raw = $field['value'];
        }
        $is_checked = ('' !== trim((string) $raw));

        $label = $is_checked ? $checked_label : $unchecked_label;
        $record->update_field($id, 'value', $label);
        $record->update_field($id, 'raw_value', $label);
    }
}
