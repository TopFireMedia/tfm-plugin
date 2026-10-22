<?php
/**
 * Make form notifications authenticate: align the envelope sender with `From`.
 *
 * SPF is checked against the **envelope sender** (`MAIL FROM`, what lands in
 * `Return-Path:`), not the `From:` header the recipient sees. PHP's `mail()`
 * passes Postfix no `-f`, so the envelope falls back to the system default —
 * on a Plesk box that is `root@<server-hostname>.plesk.page`, a domain with no
 * SPF record and no relationship to the site.
 *
 * The result, measured on playstreetfranchise.com on 22 Oct 2026:
 *
 *   From:         Play Street Museum Franchise <noreply@playstreetfranchise.com>
 *   Return-Path:  <root@inspiring-sinoussi.67-217-247-95.plesk.page>
 *   spf=none      (...plesk.page does not designate permitted sender hosts)
 *   dmarc=fail    (p=NONE) header.from=playstreetfranchise.com
 *
 * Every form notification from the fleet goes out like this. It is invisible
 * day to day only because our domains publish `p=none`, so nothing enforces the
 * failure — mail is delivered unauthenticated rather than rejected. That is the
 * absence of enforcement, not protection, and it ends the moment a recipient
 * domain tightens. It is also a strong candidate for the lead copies we already
 * know we lose to Google.
 *
 * Setting `$phpmailer->Sender` makes PHPMailer pass `-f <address>` to sendmail,
 * which sets the envelope and lets SPF evaluate the site's own domain — the one
 * whose DNS we control and which already lists the server IP.
 *
 * **This deliberately only vouches for our own domain.** The envelope is a claim
 * that this server is allowed to send as that address, so it is set only when
 * the `From` domain matches the site's own host. A plugin sending as a Gmail or
 * client-owned address is left exactly as it was: asserting an envelope we have
 * no authority for would turn a soft failure into an alignment failure on
 * somebody else's domain.
 *
 * Three guards, each for a real case:
 *
 *   - A non-empty `Sender` is never overwritten. WP Mail SMTP and friends set it
 *     themselves, and they know better than we do.
 *   - Only `Mailer === 'mail'` is touched. Under SMTP the envelope comes from
 *     the authenticated session, and `-f` is meaningless.
 *   - `www.` is stripped from both sides before comparing, so a site reached at
 *     `www.example.com` still recognises `noreply@example.com` as its own.
 *
 * ON by default, unlike the opt-in modules. Those change what a client sees in
 * an email; this changes only a header that makes existing mail authenticate,
 * and a fix nobody switches on is a fix nobody gets. Disable per site with:
 *
 *   add_filter( 'tfm_mail_envelope_enabled', '__return_false' );
 *
 * Override the address itself with the `tfm_mail_envelope_sender` filter.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Strip a leading `www.` so host comparisons are not defeated by it.
 */
function tfm_mail_envelope_base_host($host) {
    return preg_replace('/^www\./i', '', strtolower(trim((string) $host)));
}

/**
 * Set the envelope sender when, and only when, we can legitimately claim it.
 *
 * @param object $phpmailer PHPMailer instance, passed by reference by WordPress.
 */
function tfm_set_mail_envelope_sender($phpmailer) {
    if (!is_object($phpmailer)) {
        return;
    }
    if (!apply_filters('tfm_mail_envelope_enabled', true)) {
        return;
    }
    // Something already decided the envelope — an SMTP plugin, or a caller that
    // set it on purpose. Leave it alone.
    if (!empty($phpmailer->Sender)) {
        return;
    }
    // Under SMTP the envelope comes from the authenticated session; -f does nothing.
    if (isset($phpmailer->Mailer) && 'mail' !== $phpmailer->Mailer) {
        return;
    }

    $from = isset($phpmailer->From) ? (string) $phpmailer->From : '';
    if ('' === $from || !is_email($from) || false === strpos($from, '@')) {
        return;
    }

    $from_host = tfm_mail_envelope_base_host(substr(strrchr($from, '@'), 1));
    $site_host = tfm_mail_envelope_base_host(wp_parse_url(home_url(), PHP_URL_HOST));

    // Only assert an envelope for the site's own domain. See the header comment.
    if ('' === $site_host || $from_host !== $site_host) {
        return;
    }

    $sender = apply_filters('tfm_mail_envelope_sender', $from, $phpmailer);
    if (is_string($sender) && is_email($sender)) {
        $phpmailer->Sender = $sender;
    }
}
add_action('phpmailer_init', 'tfm_set_mail_envelope_sender', 99);
