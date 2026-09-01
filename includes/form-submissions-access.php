<?php
/**
 * TFM Form Submissions Access
 *
 * Gives a client a read-only, export-only view of Elementor form submissions
 * without making them an administrator.
 *
 * Elementor Pro gates its own Submissions screen (and the REST routes behind it)
 * on `manage_options`, and offers no capability for partial access. Rather than
 * filter Elementor's permission callbacks — which would break silently whenever
 * Pro reorganises them — this reads the same tables directly and ships its own
 * screen. Nothing here depends on Elementor Pro internals beyond the table shape.
 *
 * The role holds exactly two capabilities: `read` (the minimum to load wp-admin)
 * and `tfm_view_form_submissions`. There is deliberately no edit, delete or
 * resend path anywhere in this file.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

const TFM_LEADS_ROLE = 'tfm_form_submissions';
const TFM_LEADS_CAP  = 'tfm_view_form_submissions';
const TFM_LEADS_ROLE_VERSION = 1;

function tfm_leads_enabled() {
    $settings = tfm_load_settings();
    return !empty($settings['enable_form_submissions_access']);
}

/**
 * Create the role once, and re-create it if the capability set changes.
 * Guarded by a version option so this is a no-op on almost every request.
 */
function tfm_leads_register_role() {
    if (!tfm_leads_enabled()) {
        return;
    }
    if ((int) get_option('tfm_leads_role_version') === TFM_LEADS_ROLE_VERSION && get_role(TFM_LEADS_ROLE)) {
        return;
    }
    remove_role(TFM_LEADS_ROLE);
    add_role(TFM_LEADS_ROLE, 'Form Submissions', [
        'read'        => true,
        TFM_LEADS_CAP => true,
    ]);
    // Administrators keep access so support can see what the client sees.
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap(TFM_LEADS_CAP);
    }
    update_option('tfm_leads_role_version', TFM_LEADS_ROLE_VERSION);
}
add_action('init', 'tfm_leads_register_role');

/**
 * True for a user whose access is *only* the leads screen — used to decide
 * whether to strip the rest of wp-admin. An administrator holds the cap too,
 * so the check is deliberately "has the cap and can't edit posts".
 */
function tfm_leads_is_leads_only_user() {
    return current_user_can(TFM_LEADS_CAP) && !current_user_can('edit_posts') && !current_user_can('manage_options');
}

function tfm_leads_admin_menu() {
    if (!tfm_leads_enabled()) {
        return;
    }
    add_menu_page(
        'Form Submissions',
        'Form Submissions',
        TFM_LEADS_CAP,
        'tfm-form-submissions',
        'tfm_leads_render_page',
        'dashicons-feedback',
        26
    );
}
add_action('admin_menu', 'tfm_leads_admin_menu');

/**
 * Leave a leads-only user with one menu item. Runs late so it sees everything
 * other plugins have registered.
 */
function tfm_leads_strip_admin_menu() {
    if (!tfm_leads_enabled() || !tfm_leads_is_leads_only_user()) {
        return;
    }
    global $menu, $submenu;
    foreach ((array) $menu as $i => $item) {
        if (isset($item[2]) && $item[2] !== 'tfm-form-submissions') {
            unset($menu[$i]);
        }
    }
    $submenu = [];
}
add_action('admin_menu', 'tfm_leads_strip_admin_menu', 999);

/**
 * Menus only hide links; this stops a leads-only user reaching any other admin
 * screen by typing the URL. Profile stays reachable so they can set a password.
 */
function tfm_leads_confine_to_page() {
    if (!tfm_leads_enabled() || !tfm_leads_is_leads_only_user() || wp_doing_ajax()) {
        return;
    }
    global $pagenow;
    $allowed_pages = ['profile.php', 'admin-post.php', 'admin-ajax.php'];
    if (in_array($pagenow, $allowed_pages, true)) {
        return;
    }
    if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'tfm-form-submissions') {
        return;
    }
    wp_safe_redirect(admin_url('admin.php?page=tfm-form-submissions'));
    exit;
}
add_action('admin_init', 'tfm_leads_confine_to_page');

// A leads-only user has no use for the toolbar's site/comment links.
function tfm_leads_hide_admin_bar($show) {
    return tfm_leads_is_leads_only_user() ? false : $show;
}
add_filter('show_admin_bar', 'tfm_leads_hide_admin_bar');

/* ---------------------------------------------------------------------------
 * Data
 * ------------------------------------------------------------------------ */

function tfm_leads_tables() {
    global $wpdb;
    return [
        'subs'   => $wpdb->prefix . 'e_submissions',
        'values' => $wpdb->prefix . 'e_submissions_values',
    ];
}

function tfm_leads_tables_exist() {
    global $wpdb;
    $t = tfm_leads_tables();
    return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t['subs']));
}

/** Distinct forms, for the picker. */
function tfm_leads_forms() {
    global $wpdb;
    $t = tfm_leads_tables();
    return $wpdb->get_results(
        "SELECT form_name, COUNT(*) AS total FROM {$t['subs']} WHERE status != 'trash' GROUP BY form_name ORDER BY form_name ASC"
    );
}

/**
 * Elementor stores a field's *id* against each value, which is a generated
 * string like `field_9550e65` unless the editor set a custom one. The human
 * label lives in the form widget's settings on the page the form sits on, so
 * resolve it from there and cache the map.
 */
function tfm_leads_label_map($post_id) {
    $post_id = (int) $post_id;
    $cache_key = 'tfm_leads_labels_' . $post_id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $map = [];
    $data = get_post_meta($post_id, '_elementor_data', true);
    if (is_string($data) && $data !== '') {
        $tree = json_decode($data, true);
        if (is_array($tree)) {
            $walk = function ($node) use (&$walk, &$map) {
                if (isset($node['widgetType']) && $node['widgetType'] === 'form') {
                    foreach (($node['settings']['form_fields'] ?? []) as $field) {
                        $id = $field['custom_id'] ?? '';
                        $label = $field['field_label'] ?? '';
                        if ($id !== '' && $label !== '') {
                            $map[$id] = $label;
                        }
                    }
                }
                foreach (($node['elements'] ?? []) as $child) {
                    $walk($child);
                }
            };
            foreach ($tree as $section) {
                $walk($section);
            }
        }
    }

    set_transient($cache_key, $map, HOUR_IN_SECONDS);
    return $map;
}

/**
 * Submissions in a window, with their values flattened onto each row.
 * `$forms` empty means every form.
 */
function tfm_leads_fetch($from_gmt, $to_gmt, array $forms = []) {
    global $wpdb;
    $t = tfm_leads_tables();

    $where = ["s.status != 'trash'", 's.created_at_gmt >= %s', 's.created_at_gmt <= %s'];
    $args  = [$from_gmt, $to_gmt];

    if ($forms) {
        $where[] = 's.form_name IN (' . implode(',', array_fill(0, count($forms), '%s')) . ')';
        $args = array_merge($args, $forms);
    }

    $sql = "SELECT s.id, s.form_name, s.post_id, s.created_at, s.referer
            FROM {$t['subs']} s
            WHERE " . implode(' AND ', $where) . '
            ORDER BY s.id ASC';

    $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
    if (!$rows) {
        return [];
    }

    $ids = wp_list_pluck($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $values = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT submission_id, `key`, value FROM {$t['values']} WHERE submission_id IN ($placeholders)",
            $ids
        )
    );

    $by_submission = [];
    foreach ($values as $v) {
        $by_submission[$v->submission_id][$v->key] = $v->value;
    }
    foreach ($rows as $row) {
        $row->values = $by_submission[$row->id] ?? [];
    }
    return $rows;
}

/**
 * Resolve the window from the submitted preset. Returns GMT strings, because
 * that is what the table stores; the UI talks in site time.
 */
function tfm_leads_resolve_range($preset, $from_raw, $to_raw) {
    $now = current_time('timestamp', true);

    switch ($preset) {
        case 'last7':
            return [gmdate('Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS), gmdate('Y-m-d H:i:s', $now)];
        case 'last30':
            return [gmdate('Y-m-d H:i:s', $now - 30 * DAY_IN_SECONDS), gmdate('Y-m-d H:i:s', $now)];
        case 'custom':
            $from = $from_raw ? gmdate('Y-m-d H:i:s', strtotime($from_raw . ' 00:00:00') - (int) (get_option('gmt_offset') * HOUR_IN_SECONDS)) : gmdate('Y-m-d H:i:s', 0);
            $to   = $to_raw ? gmdate('Y-m-d H:i:s', strtotime($to_raw . ' 23:59:59') - (int) (get_option('gmt_offset') * HOUR_IN_SECONDS)) : gmdate('Y-m-d H:i:s', $now);
            return [$from, $to];
        case 'since_last':
        default:
            // The point of this preset: no gaps and no repeats, whenever they
            // happen to come back. Falls back to 30 days on a first download.
            $last = get_user_meta(get_current_user_id(), 'tfm_leads_last_export_gmt', true);
            $from = $last ? $last : gmdate('Y-m-d H:i:s', $now - 30 * DAY_IN_SECONDS);
            return [$from, gmdate('Y-m-d H:i:s', $now)];
    }
}

/* ---------------------------------------------------------------------------
 * Export
 * ------------------------------------------------------------------------ */

function tfm_leads_handle_export() {
    if (!current_user_can(TFM_LEADS_CAP) || !tfm_leads_enabled()) {
        wp_die('You do not have permission to export form submissions.', 403);
    }
    check_admin_referer('tfm_leads_export');

    $preset = isset($_POST['preset']) ? sanitize_key($_POST['preset']) : 'since_last';
    $from_raw = isset($_POST['from']) ? sanitize_text_field(wp_unslash($_POST['from'])) : '';
    $to_raw   = isset($_POST['to']) ? sanitize_text_field(wp_unslash($_POST['to'])) : '';
    $forms    = isset($_POST['forms']) ? array_map('sanitize_text_field', wp_unslash((array) $_POST['forms'])) : [];

    list($from_gmt, $to_gmt) = tfm_leads_resolve_range($preset, $from_raw, $to_raw);
    $rows = tfm_leads_fetch($from_gmt, $to_gmt, $forms);

    // Union of every field across the range, so forms with different shapes can
    // share one file; a form that lacks a column simply leaves it blank.
    //
    // Keyed by *label*, not by field id: each form generates its own id for the
    // same question (field_404ecf0 / field_6d5360a / field_aff0781 are all "How
    // much liquid capital…"), so keying by id repeats the column once per form.
    $columns = [];
    $seen = [];
    foreach ($rows as $row) {
        $labels = tfm_leads_label_map($row->post_id);
        foreach (array_keys($row->values) as $key) {
            $label = $labels[$key] ?? $key;
            // Forms label the same question inconsistently ("State" vs "State*",
            // where the asterisk is just a required marker). Fold those together
            // but keep the first spelling we saw as the visible header.
            $canonical = strtolower(trim(preg_replace('/\s+/', ' ', rtrim(trim($label), " *:"))));
            if (!isset($seen[$canonical])) {
                $seen[$canonical] = $label;
            }
            $columns[$seen[$canonical]][$key] = true;
        }
    }

    $filename = 'form-submissions-' . gmdate('Y-m-d') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Streamed straight to the client — the file never exists on disk, so there
    // is no stray CSV of leads sitting in uploads/ with a guessable URL.
    $out = fopen('php://output', 'w');
    fputcsv($out, array_merge(['Submitted', 'Form', 'Page'], array_keys($columns)));

    foreach ($rows as $row) {
        $line = [
            get_date_from_gmt($row->created_at, 'Y-m-d H:i:s'),
            $row->form_name,
            $row->referer,
        ];
        foreach ($columns as $keys) {
            // A label can map to several field ids across forms; this row will
            // only have filled one of them.
            $value = '';
            foreach (array_keys($keys) as $key) {
                if (isset($row->values[$key]) && $row->values[$key] !== '') {
                    $value = $row->values[$key];
                    break;
                }
            }
            // Acceptance fields store the raw checkbox value; "on" means nothing
            // to someone reading the spreadsheet.
            $line[] = ($value === 'on') ? 'Yes' : $value;
        }
        fputcsv($out, $line);
    }
    fclose($out);

    tfm_leads_record_export(count($rows), $from_gmt, $to_gmt);
    exit;
}
add_action('admin_post_tfm_leads_export', 'tfm_leads_handle_export');

/**
 * Watermark for "since my last download", plus a short audit trail — with
 * client PII leaving the site, who pulled what and when is worth keeping.
 */
function tfm_leads_record_export($count, $from_gmt, $to_gmt) {
    $user = wp_get_current_user();
    update_user_meta($user->ID, 'tfm_leads_last_export_gmt', gmdate('Y-m-d H:i:s'));

    $log = get_option('tfm_leads_export_log', []);
    if (!is_array($log)) {
        $log = [];
    }
    array_unshift($log, [
        'time'  => gmdate('Y-m-d H:i:s'),
        'user'  => $user->user_login,
        'rows'  => (int) $count,
        'from'  => $from_gmt,
        'to'    => $to_gmt,
    ]);
    update_option('tfm_leads_export_log', array_slice($log, 0, 100), false);
}

/* ---------------------------------------------------------------------------
 * Screen
 * ------------------------------------------------------------------------ */

function tfm_leads_render_page() {
    if (!current_user_can(TFM_LEADS_CAP)) {
        wp_die('You do not have permission to view form submissions.', 403);
    }

    if (!tfm_leads_tables_exist()) {
        echo '<div class="wrap"><h1>Form Submissions</h1><div class="notice notice-error"><p>'
            . 'Elementor form submission tables were not found on this site.</p></div></div>';
        return;
    }

    $forms = tfm_leads_forms();
    $last  = get_user_meta(get_current_user_id(), 'tfm_leads_last_export_gmt', true);
    $preview_from = tfm_leads_resolve_range('since_last', '', '');
    $preview_rows = tfm_leads_fetch($preview_from[0], $preview_from[1]);
    ?>
    <div class="wrap">
        <h1>Form Submissions</h1>
        <p>Download form submissions as a CSV. Choose a date range and which forms to include.</p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="tfm_leads_export">
            <?php wp_nonce_field('tfm_leads_export'); ?>

            <h2 class="title">Date range</h2>
            <p>
                <label><input type="radio" name="preset" value="since_last" checked>
                    <strong>Since my last download</strong>
                    <?php if ($last) : ?>
                        <span class="description">— last downloaded <?php echo esc_html(get_date_from_gmt($last, 'j M Y, g:ia')); ?>
                            (<?php echo count($preview_rows); ?> new since then)</span>
                    <?php else : ?>
                        <span class="description">— you haven't downloaded before, so this covers the last 30 days</span>
                    <?php endif; ?>
                </label><br>
                <label><input type="radio" name="preset" value="last7"> Last 7 days</label><br>
                <label><input type="radio" name="preset" value="last30"> Last 30 days</label><br>
                <label><input type="radio" name="preset" value="custom"> Custom range:</label>
                <input type="date" name="from"> to <input type="date" name="to">
            </p>

            <h2 class="title">Forms</h2>
            <p class="description">All forms are included unless you narrow it down.</p>
            <p>
                <?php foreach ($forms as $form) : ?>
                    <label style="display:inline-block;min-width:260px;margin:2px 0">
                        <input type="checkbox" name="forms[]" value="<?php echo esc_attr($form->form_name); ?>" checked>
                        <?php echo esc_html($form->form_name); ?>
                        <span class="description">(<?php echo (int) $form->total; ?>)</span>
                    </label><br>
                <?php endforeach; ?>
            </p>

            <?php submit_button('Download CSV'); ?>
        </form>

        <?php if (current_user_can('manage_options')) : ?>
            <?php $log = get_option('tfm_leads_export_log', []); ?>
            <?php if (is_array($log) && $log) : ?>
                <h2 class="title">Recent downloads</h2>
                <table class="widefat striped" style="max-width:760px">
                    <thead><tr><th>When</th><th>User</th><th>Rows</th><th>Range covered</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($log, 0, 10) as $entry) : ?>
                        <tr>
                            <td><?php echo esc_html(get_date_from_gmt($entry['time'], 'j M Y, g:ia')); ?></td>
                            <td><?php echo esc_html($entry['user']); ?></td>
                            <td><?php echo (int) $entry['rows']; ?></td>
                            <td><?php echo esc_html(substr($entry['from'], 0, 10) . ' → ' . substr($entry['to'], 0, 10)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
