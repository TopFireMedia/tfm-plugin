<?php
/**
 * Elementor Forms — route paid-social leads to a different CRM parsing subject.
 *
 * FranConnect decides a lead's source from the *subject line* of the
 * notification email: each Lead Source Detail is configured with a parsing
 * subject, and whichever one matches wins. A site that sends one subject for
 * every submission therefore files Meta-paid leads and organic leads under the
 * same source, no matter what the email body says.
 *
 * Play Street Museum is the first case. FranConnect already has both
 * classifications configured under Internet:
 *
 *   Play Street Museum Website  ->  "TopFire Media Web Lead: Play Street Museum Franchise"
 *   Meta Marketing              ->  "TopFire Media Meta Lead: Play Street Museum"
 *
 * Nothing was wrong in the CRM. The website simply never sent the second
 * subject, so Meta leads arrived under the website source.
 *
 * This module rewrites `email_subject` when, and only when, both are true:
 *
 *   1. the outgoing subject is *exactly* the configured "from" subject, and
 *   2. the visitor's stored `utm_source` is one of the Meta values.
 *
 * Both conditions are deliberate, and condition 1 is the important one.
 *
 * **Why match the subject rather than rewrite whatever is there.** A form
 * typically fires more than one notification. Play Street Museum sends a second
 * copy to web@topfiremedia.com with the subject `New message from "BRAND
 * Franchise"`, which FranConnect never sees. Rewriting by recipient, or
 * rewriting unconditionally, would corrupt that internal copy too. Matching the
 * exact subject means this module touches precisely one email.
 *
 * It also answers a question the brief left open. The audit found **four**
 * different subjects in use across the site's forms, so "everything else keeps
 * the existing Web Lead subject" had no single meaning. Here it needs none:
 * anything that is not the configured subject is passed through untouched, so
 * the other three keep whatever they already send and no routing changes by
 * accident.
 *
 * **Medium is not checked.** An earlier draft of the brief said to require a
 * paid medium. The confirmed campaign structure uses `utm_medium=Social`, which
 * that test would reject, so the rule matches on source alone. Add a medium
 * condition per site with the `tfm_form_meta_subject_match` filter if a client's
 * tagging ever needs it.
 *
 * UTM values are never modified — the lines appended by elementor-form-utm.php
 * are left exactly as they are, so the CRM still receives the original campaign
 * data alongside the new subject.
 *
 * OFF by default, and off for any site that has not filled in both subjects:
 * they are client-specific strings, so there is nothing sensible to default
 * them to. Set them at TFM Custom Functions → General → "Meta Lead Subject
 * Routing". Requires "Form UTM Attribution" to be enabled, because without the
 * cookie there is no attribution to route on once a visitor leaves the landing
 * page.
 *
 * Filters:
 *   tfm_form_meta_sources         — the utm_source values treated as Meta.
 *   tfm_form_meta_subject_config  — the from/to pair, to set it in code instead.
 *   tfm_form_meta_subject_match   — final yes/no, for per-site conditions.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The `utm_source` values that mean "Meta paid".
 *
 * Compared lower-cased and trimmed, so `Meta`, `META` and `meta` all match —
 * the brief asks for case-insensitive matching because the live campaigns tag
 * `utm_source=Meta` while older rules and documentation use `meta`.
 */
function tfm_form_meta_sources() {
    return apply_filters('tfm_form_meta_sources', array(
        'meta',
        'facebook',
        'fb',
        'ig',
        'instagram',
    ));
}

/**
 * The configured subject pair for this site.
 *
 * `from` is the subject FranConnect currently parses as the website source;
 * `to` is the subject it parses as Meta Marketing. Both must be non-empty for
 * the rule to do anything — a half-configured site is treated as off rather
 * than guessing.
 *
 * @return array{from:string,to:string}
 */
function tfm_form_meta_subject_config() {
    $settings = function_exists('tfm_load_settings') ? tfm_load_settings() : array();

    $config = array(
        'from' => isset($settings['elementor_form_meta_subject_from'])
            ? trim((string) $settings['elementor_form_meta_subject_from'])
            : '',
        'to' => isset($settings['elementor_form_meta_subject_to'])
            ? trim((string) $settings['elementor_form_meta_subject_to'])
            : '',
    );

    $config = apply_filters('tfm_form_meta_subject_config', $config);

    return array(
        'from' => isset($config['from']) ? trim((string) $config['from']) : '',
        'to'   => isset($config['to']) ? trim((string) $config['to']) : '',
    );
}

/**
 * Is this submission attributable to Meta?
 *
 * Reads the same resolved values the UTM module writes into the email, so the
 * subject and the `UTM Source:` line can never disagree — they come from one
 * source of truth, including its cookie-then-query-string precedence.
 */
function tfm_form_meta_subject_is_meta_lead() {
    if (!function_exists('tfm_form_utm_values')) {
        return false;
    }

    $values = tfm_form_utm_values();
    $source = isset($values['utm_source']) ? strtolower(trim((string) $values['utm_source'])) : '';

    if ('' === $source) {
        return false;
    }

    return in_array($source, array_map('strtolower', tfm_form_meta_sources()), true);
}

/**
 * Swap the subject on the one email FranConnect parses.
 *
 * Priority 20 so the UTM module (priority 10) has already appended its lines;
 * this only reads `email_subject` and never touches the body, the recipients or
 * any other field.
 *
 * @param array  $fields Email fields: email_to, email_subject, email_content, …
 * @param object $record Elementor Pro Form_Record (unused).
 * @return array
 */
function tfm_form_meta_subject_route($fields, $record) {
    if (!is_array($fields) || !isset($fields['email_subject'])) {
        return $fields;
    }

    // Routing without attribution would be worse than no routing: with the UTM
    // module off, nothing writes the cookie, so a visitor who browsed before
    // converting looks like direct traffic and would silently keep the website
    // subject. Require the module rather than half-work.
    if (!function_exists('tfm_form_utm_is_enabled') || !tfm_form_utm_is_enabled()) {
        return $fields;
    }

    $config = tfm_form_meta_subject_config();
    if ('' === $config['from'] || '' === $config['to']) {
        return $fields;
    }

    $subject = trim((string) $fields['email_subject']);

    // Exact match, case-insensitive. Anything else is another form's
    // notification or the internal copy, and is left alone.
    $matches_subject = (0 === strcasecmp($subject, $config['from']));

    $should_route = apply_filters(
        'tfm_form_meta_subject_match',
        ($matches_subject && tfm_form_meta_subject_is_meta_lead()),
        $fields,
        $record,
        $config
    );

    if ($should_route) {
        $fields['email_subject'] = $config['to'];
    }

    return $fields;
}
add_filter('elementor_pro/forms/wp_mail_fields', 'tfm_form_meta_subject_route', 20, 2);
