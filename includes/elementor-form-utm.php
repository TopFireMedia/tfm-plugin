<?php
/**
 * Elementor Forms — carry UTM attribution into every submission.
 *
 * Franchise CRMs (FranConnect across most of the fleet) attribute a lead from
 * `Field Name: Value` lines in the notification email. Getting the UTM values
 * into those lines has two problems, and the obvious solution solves neither.
 *
 * The obvious solution is Elementor's own Hidden field with the "Request
 * Parameter" dynamic tag, which reads `?utm_source=` off the current URL. It
 * needs no code — and it is wrong here, because it reads the URL of the page
 * holding the form, not the page the visitor landed on. A prospect arrives on
 * `/?utm_source=meta`, browses to `/franchise-opportunity`, submits there, and
 * every UTM field arrives empty. Franchise prospects almost never convert on
 * the landing page, so most paid leads would be recorded as direct — and it
 * would look like it was working, which is worse than not having it.
 *
 * So attribution has to survive navigation:
 *
 *   1. A small script stores any `utm_*` seen in the query string in a
 *      first-party cookie. **Last touch** — a later campaign overwrites an
 *      earlier one, the agreed model.
 *   2. This module appends the values to the notification email via
 *      `elementor_pro/forms/wp_mail_fields`, in the `Field Name: Value` shape
 *      the CRM parses.
 *
 * Because the cookie travels with the submit request, nothing has to be added
 * to any form — a site gets UTM attribution from the plugin update alone, with
 * no form editing at all, whether its email body is `[all-fields]` or a
 * hand-written list of lines.
 *
 * Filtering the email rather than adding fields to the record is deliberate and
 * was not the first design. `Form_Record` has no `add_field()` — verified
 * against Elementor Pro 4.2.3, which exposes only `get`, `update_field` and
 * `remove_field` — so injected fields are not possible without reaching into
 * protected state that the next Elementor release could rename. The email
 * filter is a documented, supported hook and gives exact control over the
 * format, which matters when a parser downstream depends on it.
 *
 * The trade-off: the values reach the notification email, not the saved
 * submission or a webhook. Where a form *does* declare its own `utm_*` fields,
 * they are filled in too, so those sites get them everywhere.
 *
 * Empty values are deliberate: direct traffic produces `UTM Source:` with
 * nothing after it, which is what the CRM expects, and mirrors how Elementor
 * already renders a declared-but-empty field.
 *
 * OFF by default. It adds five lines to every notification email on the site,
 * which is a client-visible change, so it is opted into per site exactly like
 * "Form Acceptance Yes/No". Turn it on at
 * TFM Custom Functions → General → "Form UTM Attribution", or with:
 *
 *   wp option update tfm_form_utm_enabled 1
 *
 * Force on/off in code: add_filter( 'tfm_form_utm_enabled', '__return_true' );
 * Change the labels:    add_filter( 'tfm_form_utm_labels', function ( $l ) { … } );
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Cookie holding the last-touch UTM set. First-party, no third party involved. */
if (!defined('TFM_UTM_COOKIE')) {
    define('TFM_UTM_COOKIE', 'tfm_utm');
}

/** How long attribution survives. 90 days matches the usual paid-media window. */
if (!defined('TFM_UTM_TTL_DAYS')) {
    define('TFM_UTM_TTL_DAYS', 90);
}

/**
 * The parameters carried, mapped to the label the CRM parses.
 *
 * The label is the whole contract: `[all-fields]` renders `Label: value`, and
 * FranConnect matches on that label. Changing one silently breaks parsing for
 * every site, which is why it is a filterable constant rather than inline text.
 */
function tfm_form_utm_labels() {
    return apply_filters('tfm_form_utm_labels', array(
        'utm_source'   => 'UTM Source',
        'utm_medium'   => 'UTM Medium',
        'utm_campaign' => 'UTM Campaign',
        'utm_content'  => 'UTM Content',
        'utm_term'     => 'UTM Term',
    ));
}

/** Per-site opt-in, matching the acceptance module's pattern. */
function tfm_form_utm_is_enabled() {
    $settings = function_exists('tfm_load_settings')
        ? tfm_load_settings()
        : get_option('tfm_plugin_settings', array());

    $enabled = !empty($settings['elementor_form_utm'])
        || (bool) get_option('tfm_form_utm_enabled', false);

    return (bool) apply_filters('tfm_form_utm_enabled', $enabled);
}

/**
 * Clean one UTM value.
 *
 * These arrive from the query string, so anyone can put anything in them, and
 * they end up interpolated into an HTML email sent to a CRM inbox and BCC'd to
 * staff. Tags are stripped rather than escaped because a UTM value has no
 * legitimate use for markup, and the length cap stops a crafted URL from
 * bloating the cookie or the email.
 */
function tfm_form_utm_clean($value) {
    $value = wp_strip_all_tags((string) $value, true);
    $value = preg_replace('/[^\p{L}\p{N} ._\-\/|+%:@]/u', '', $value);
    $value = trim(preg_replace('/\s+/', ' ', (string) $value));

    return mb_substr($value, 0, 200);
}

/**
 * The stored UTM set for this request.
 *
 * Reads the cookie the front-end script wrote. Falls back to the current query
 * string so a visitor who submits on the landing page itself is still
 * attributed even if the cookie was blocked.
 */
function tfm_form_utm_values() {
    $out = array();
    $keys = array_keys(tfm_form_utm_labels());

    // Query-string format, not JSON. WordPress slashes $_COOKIE via
    // wp_magic_quotes(), so the value has to be unslashed before parsing — and
    // unslashing JSON destroys it: a UTM value containing a double quote is
    // stored as \" , unslashing leaves a bare " , and the whole object fails to
    // decode. One quote in one parameter silently wiped all five values.
    // parse_str has no such collision: URLSearchParams percent-encodes on the
    // way in, so there are no backslashes in the cookie at all.
    $stored = array();
    if (!empty($_COOKIE[TFM_UTM_COOKIE])) {
        parse_str(wp_unslash($_COOKIE[TFM_UTM_COOKIE]), $stored);
        if (!is_array($stored)) {
            $stored = array();
        }
    }

    foreach ($keys as $key) {
        // The live query string wins: it is this request's truth, and last
        // touch is the agreed model.
        if (isset($_GET[$key]) && '' !== trim((string) $_GET[$key])) {
            $out[$key] = tfm_form_utm_clean($_GET[$key]);
        } elseif (isset($stored[$key])) {
            $out[$key] = tfm_form_utm_clean($stored[$key]);
        } else {
            $out[$key] = '';
        }
    }

    return $out;
}

/**
 * Fill any UTM fields the form itself declares.
 *
 * Only relevant to sites that added their own hidden `utm_*` fields before this
 * module existed: those keep working, and because the value goes into the
 * record it reaches the saved submission and any webhook as well as the email.
 * Forms with no such fields are untouched here and are handled by the email
 * filter below.
 *
 * `elementor_pro/forms/process` fires at the end of Form_Record::process_fields()
 * and therefore before any action reads the record — the same hook Elementor's
 * own Upload field uses, and the one elementor-form-acceptance.php relies on.
 *
 * @param object $record       Elementor Pro Form_Record.
 * @param object $ajax_handler Elementor Pro Ajax_Handler (unused).
 */
function tfm_form_utm_fill_declared($record, $ajax_handler) {
    if (!tfm_form_utm_is_enabled()) {
        return;
    }
    if (!is_object($record) || !method_exists($record, 'get') || !method_exists($record, 'update_field')) {
        return;
    }

    $fields = $record->get('fields');
    if (!is_array($fields)) {
        return;
    }

    $values = tfm_form_utm_values();

    foreach ($values as $key => $value) {
        if (!isset($fields[$key])) {
            continue;
        }
        $record->update_field($key, 'value', $value);
        $record->update_field($key, 'raw_value', $value);
    }
}
add_action('elementor_pro/forms/process', 'tfm_form_utm_fill_declared', 10, 2);

/**
 * Append the UTM lines to the notification email.
 *
 * `wp_mail_fields` is the last hook before wp_mail() that still carries the
 * record, and by this point Elementor has already resolved `[all-fields]` and
 * appended its own metadata block after a `---` separator. The lines are
 * inserted *before* that separator so campaign data sits with the submission
 * rather than trailing after Remote IP and User Agent.
 *
 * Line-break style is read off the content rather than the settings: an email
 * action can be configured plain or HTML, a form can have two of them with
 * different settings, and the filter does not say which one is running. The
 * content itself is unambiguous.
 *
 * @param array  $fields Email fields: email_to, email_subject, email_content, …
 * @param object $record Elementor Pro Form_Record (unused; values come from the cookie).
 * @return array
 */
function tfm_form_utm_email_lines($fields, $record) {
    if (!tfm_form_utm_is_enabled() || !is_array($fields) || !isset($fields['email_content'])) {
        return $fields;
    }

    $content = (string) $fields['email_content'];
    $is_html = $content !== wp_strip_all_tags($content);
    $labels = tfm_form_utm_labels();
    $values = tfm_form_utm_values();

    $lines = array();
    foreach ($labels as $key => $label) {
        $value = isset($values[$key]) ? $values[$key] : '';
        // Empty values are sent deliberately: direct traffic yields
        // "UTM Source:" with nothing after it, which is what the CRM expects
        // and keeps the line set identical on every submission.
        $lines[] = $is_html ? esc_html($label . ': ' . $value) : $label . ': ' . $value;
    }

    // `<br>`, not `<p>`. Elementor separates every line it generates with
    // `$line_break = $send_html ? '<br>' : "\n"` — both `[all-fields]` and the
    // metadata block — and FranConnect's parser splits on that. A real
    // submission proved the difference: every `<br>`-separated line was read
    // (the form fields mapped to CRM fields, the metadata landed in Comments),
    // while `<p>`-wrapped lines vanished entirely, parsed as one unreadable
    // blob. Matching Elementor's own convention is the whole fix.
    $break = $is_html ? '<br>' : "\n";
    $block = $break . implode($break, $lines);

    // Elementor separates its metadata block with a "---" line; put the UTM
    // lines above it when it is present, else simply append.
    $sep = $is_html ? '<br>---<br>' : "\n---\n";
    $at = strpos($content, $sep);
    $fields['email_content'] = (false === $at)
        ? $content . $block
        : substr($content, 0, $at) . $block . substr($content, $at);

    return $fields;
}
add_filter('elementor_pro/forms/wp_mail_fields', 'tfm_form_utm_email_lines', 10, 2);

/**
 * Front-end capture.
 *
 * Deliberately inline, tiny and in the footer: it must run on every page to
 * catch the landing hit, and an extra HTTP request for ~15 lines is not worth
 * it on sites where page weight is already a QC finding.
 *
 * Page-cache safe — it reads `location.search` in the browser, so a cached HTML
 * page still records the right campaign. That is the other reason not to use
 * Elementor's server-side Request Parameter tag, which a page cache can freeze
 * to whichever visitor happened to prime it.
 */
function tfm_form_utm_script() {
    if (!tfm_form_utm_is_enabled()) {
        return;
    }

    $keys = wp_json_encode(array_keys(tfm_form_utm_labels()));
    $name = TFM_UTM_COOKIE;
    $days = (int) TFM_UTM_TTL_DAYS;
    $secure = is_ssl() ? '; secure' : '';
    ?>
<script>
(function () {
  try {
    var keys = <?php echo $keys; ?>, q = new URLSearchParams(location.search), found = new URLSearchParams(), any = false;
    keys.forEach(function (k) {
      var v = q.get(k);
      if (v && v.trim()) { found.set(k, v.trim().slice(0, 200)); any = true; }
    });
    // Last touch: only overwrite when this hit actually carries campaign data,
    // so ordinary internal navigation never clears an existing attribution.
    if (!any) return;
    var d = new Date();
    d.setTime(d.getTime() + <?php echo $days; ?> * 864e5);
    // Stored as a query string, not JSON: the server has to unslash the cookie,
    // and unslashing JSON breaks on any escaped quote. URLSearchParams
    // percent-encodes, so nothing in the cookie needs escaping.
    document.cookie = <?php echo wp_json_encode($name); ?> + '=' + found.toString() +
      ';expires=' + d.toUTCString() + ';path=/;samesite=lax<?php echo $secure; ?>';
  } catch (e) { /* attribution is never worth breaking a page for */ }
})();
</script>
    <?php
}
add_action('wp_footer', 'tfm_form_utm_script', 5);
