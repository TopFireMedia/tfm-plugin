<?php
/**
 * Consent enforcement for TFM Cookie Consent.
 *
 * The banner on its own only records a preference. This class makes the
 * preference actually take effect:
 *
 *   1. Google Consent Mode v2 — signals denied-by-default before any Google
 *      tag loads, then updates when the visitor chooses.
 *   2. Prior blocking — third-party <script> and <iframe> tags are neutered
 *      server-side (type="text/plain" / src moved to data-tfm-src) so they are
 *      never fetched or executed until the matching category is granted.
 *   3. Global Privacy Control — an opt-out signal from the browser is honoured
 *      without the visitor having to touch the banner.
 *
 * Blocking is unconditional server-side and released client-side. That is
 * deliberate: the server cannot reliably know the visitor's stored choice
 * before output starts, so we fail closed rather than open.
 *
 * @package TFM_Cookie_Consent
 */

if (!defined('ABSPATH')) {
    exit;
}

class TFM_Cookie_Consent_Enforcement {

    /**
     * Storage key shared with frontend.js.
     */
    const STORAGE_KEY = 'tfm_cookie_consent';

    /**
     * Built-in src/content patterns mapped to the category that gates them.
     * Matched case-insensitively as substrings, so they survive query strings
     * and CDN path changes.
     */
    private static $builtin_patterns = array(
        // Analytics
        'googletagmanager.com/gtag/js'   => 'analytics',
        'googletagmanager.com/gtm.js'    => 'analytics',
        'google-analytics.com'           => 'analytics',
        'analytics.google.com'           => 'analytics',
        'clarity.ms'                     => 'analytics',
        'static.hotjar.com'              => 'analytics',
        'script.hotjar.com'              => 'analytics',
        'plausible.io'                   => 'analytics',
        'matomo.js'                      => 'analytics',
        // Marketing / advertising
        'doubleclick.net'                => 'marketing',
        'googleadservices.com'           => 'marketing',
        'googlesyndication.com'          => 'marketing',
        'connect.facebook.net'           => 'marketing',
        'facebook.com/tr'                => 'marketing',
        'snap.licdn.com'                 => 'marketing',
        'ads.linkedin.com'               => 'marketing',
        'bat.bing.com'                   => 'marketing',
        'analytics.tiktok.com'           => 'marketing',
        'sc-static.net'                  => 'marketing',
        'ct.pinterest.com'               => 'marketing',
        // Inline snippet signatures (no src to match on)
        'gtag(\'config\''                => 'analytics',
        'fbq(\'init\''                   => 'marketing',
        '_linkedin_partner_id'           => 'marketing',
        '$wc_leads'                      => 'marketing',
    );

    /**
     * Iframe hosts gated behind the marketing category.
     */
    private static $builtin_iframe_patterns = array(
        'youtube.com/embed'      => 'marketing',
        'youtube-nocookie.com'   => 'marketing',
        'player.vimeo.com'       => 'marketing',
        'google.com/maps/embed'  => 'functional',
    );

    public function __construct() {
        $settings = get_option('tfm_cookie_consent_settings', array());
        if (empty($settings['enabled'])) {
            return;
        }

        $consent_mode  = !empty($settings['consent_mode']);
        $block_scripts = !empty($settings['prior_blocking']);
        $block_iframes = !empty($settings['block_iframes']);

        // Every hook below is gated on its own setting. Nothing is registered
        // unless it was deliberately switched on, so updating the plugin cannot
        // change what an existing site does.

        if ($consent_mode) {
            // Consent Mode must be on the page before any Google tag. Priority 0
            // on wp_head is the earliest hook available in the document head.
            add_action('wp_head', array($this, 'output_consent_mode_default'), 0);
        }

        if ($block_scripts) {
            add_filter('script_loader_tag', array($this, 'filter_enqueued_script'), 999, 3);
        }

        // The output buffer serves both script and iframe rewriting, so either
        // one needs it. Previously iframe blocking silently did nothing unless
        // script blocking happened to be on as well.
        if ($block_scripts || $block_iframes) {
            add_action('template_redirect', array($this, 'start_buffer'), 1);
        }

        // The release runtime pushes a Consent Mode update. Registering it when
        // no enforcement is active would restrict a gtag instance that another
        // plugin (Site Kit, GTM) put on the page, so it is gated too.
        if ($consent_mode || $block_scripts || $block_iframes) {
            add_action('wp_footer', array($this, 'output_release_runtime'), 5);
        }

        // Harmless with enforcement off — it only reopens the banner.
        add_shortcode('tfm_privacy_choices', array($this, 'privacy_choices_shortcode'));
    }

    /* ---------------------------------------------------------------------
     * Consent Mode v2
     * ------------------------------------------------------------------ */

    /**
     * Whether the browser sent a Global Privacy Control opt-out signal.
     */
    public static function gpc_signal() {
        return isset($_SERVER['HTTP_SEC_GPC']) && '1' === $_SERVER['HTTP_SEC_GPC'];
    }

    /**
     * Emit the gtag stub plus consent defaults.
     *
     * Defaults are computed client-side from stored consent in the same inline
     * block, so a returning visitor who accepted does not get a denied->granted
     * flicker (which Google counts as a lost measurement window).
     */
    public function output_consent_mode_default() {
        $settings = get_option('tfm_cookie_consent_settings', array());
        if (empty($settings['consent_mode'])) {
            return;
        }

        $region      = isset($settings['consent_mode_region']) ? trim($settings['consent_mode_region']) : '';
        $url_passthr = !empty($settings['consent_mode_url_passthrough']);
        $redact_ads  = !isset($settings['consent_mode_ads_data_redaction']) || $settings['consent_mode_ads_data_redaction'];
        $gpc         = self::gpc_signal() ? 'true' : 'false';

        // Optional region scoping: "GB,DE,FR" limits the denied default to those
        // regions. Empty means apply globally, which is the safer default.
        $regions_js = '';
        if ('' !== $region) {
            $codes = array_filter(array_map('trim', explode(',', $region)));
            if (!empty($codes)) {
                $regions_js = 'region: ' . wp_json_encode(array_values($codes)) . ",\n        ";
            }
        }
        ?>
<!-- TFM Cookie Consent: Google Consent Mode v2 -->
<script data-tfm-noblock="1">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
(function () {
    var KEY = <?php echo wp_json_encode(self::STORAGE_KEY); ?>;
    var gpcHeader = <?php echo $gpc; ?>;
    var gpcNav = (typeof navigator !== 'undefined' && navigator.globalPrivacyControl === true);
    var gpc = gpcHeader || gpcNav;

    function read() {
        try {
            var raw = window.localStorage.getItem(KEY) || window.sessionStorage.getItem(KEY);
            if (!raw) { return null; }
            var d = JSON.parse(raw);
            if (!d || !d.timestamp) { return null; }
            if (d.expires && Date.now() > d.expires) { return null; }
            return d;
        } catch (e) { return null; }
    }

    var stored = read();
    var cats = (stored && stored.categories) ? stored.categories : {};
    // No stored choice -> everything non-essential is denied.
    var analytics  = !!cats.analytics;
    var marketing  = !!cats.marketing;
    var functional = !!cats.functional;

    // A GPC signal is an opt-out of sale/sharing; it overrides a stored grant
    // for advertising purposes but leaves first-party analytics alone.
    if (gpc) { marketing = false; }

    var state = {
        <?php echo $regions_js; // phpcs:ignore ?>
        'ad_storage':             marketing  ? 'granted' : 'denied',
        'ad_user_data':           marketing  ? 'granted' : 'denied',
        'ad_personalization':     marketing  ? 'granted' : 'denied',
        'analytics_storage':      analytics  ? 'granted' : 'denied',
        'functionality_storage':  functional ? 'granted' : 'denied',
        'personalization_storage':functional ? 'granted' : 'denied',
        'security_storage':       'granted'
    };

    gtag('consent', 'default', state);
<?php if ($url_passthr) : ?>
    gtag('set', 'url_passthrough', true);
<?php endif; ?>
<?php if ($redact_ads) : ?>
    gtag('set', 'ads_data_redaction', true);
<?php endif; ?>

    window.tfmConsentState = state;
    window.tfmGpcActive = gpc;
})();
</script>
        <?php
    }

    /* ---------------------------------------------------------------------
     * Prior blocking
     * ------------------------------------------------------------------ */

    /**
     * Merge built-in patterns with any the admin added.
     */
    public static function get_patterns() {
        $settings = get_option('tfm_cookie_consent_settings', array());
        $patterns = self::$builtin_patterns;

        // Custom patterns arrive as "needle|category" lines. Vendors that serve
        // from rotating hostnames (WhatConverts, some CDN-fronted tags) can only
        // be caught this way.
        if (!empty($settings['blocked_script_patterns'])) {
            $lines = preg_split('/\r\n|\r|\n/', $settings['blocked_script_patterns']);
            foreach ($lines as $line) {
                $line = trim($line);
                if ('' === $line || 0 === strpos($line, '#')) {
                    continue;
                }
                $parts    = array_map('trim', explode('|', $line));
                $needle   = $parts[0];
                $category = isset($parts[1]) && '' !== $parts[1] ? $parts[1] : 'marketing';
                if ('' !== $needle) {
                    $patterns[$needle] = $category;
                }
            }
        }

        return apply_filters('tfm_cookie_consent_blocked_patterns', $patterns);
    }

    public static function get_iframe_patterns() {
        return apply_filters('tfm_cookie_consent_blocked_iframe_patterns', self::$builtin_iframe_patterns);
    }

    /**
     * Return the gating category for a haystack, or false if it is not gated.
     */
    private static function match_category($haystack, $patterns) {
        $haystack = strtolower($haystack);
        foreach ($patterns as $needle => $category) {
            if (false !== strpos($haystack, strtolower($needle))) {
                return $category;
            }
        }
        return false;
    }

    /**
     * Neuter enqueued third-party scripts.
     */
    public function filter_enqueued_script($tag, $handle, $src) {
        if (0 === strpos($handle, 'tfm-cookie-consent')) {
            return $tag;
        }

        $category = self::match_category($src, self::get_patterns());
        if (false === $category) {
            return $tag;
        }

        return self::neuter_script_tag($tag, $category);
    }

    /**
     * Rewrite a <script> tag so the browser neither fetches nor runs it.
     *
     * A non-JavaScript `type` is sufficient per the HTML spec — the src is not
     * requested — so the original src is left in place for easy re-activation.
     */
    private static function neuter_script_tag($tag, $category) {
        if (false !== stripos($tag, 'data-tfm-consent=')) {
            return $tag;
        }
        // Replace an existing type attribute, or add one.
        if (preg_match('/\stype\s*=\s*["\'][^"\']*["\']/i', $tag)) {
            $tag = preg_replace(
                '/\stype\s*=\s*["\'][^"\']*["\']/i',
                ' type="text/plain"',
                $tag,
                1
            );
        } else {
            $tag = preg_replace('/<script\b/i', '<script type="text/plain"', $tag, 1);
        }

        return preg_replace(
            '/<script\b/i',
            '<script data-tfm-consent="' . esc_attr($category) . '"',
            $tag,
            1
        );
    }

    public function start_buffer() {
        if (is_admin() || is_feed() || is_robots()) {
            return;
        }
        // REST/AJAX and any non-HTML response must pass through untouched.
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        ob_start(array($this, 'rewrite_document'));
    }

    /**
     * Rewrite third-party script and iframe tags in the finished document.
     */
    public function rewrite_document($html) {
        if ('' === trim($html) || false === stripos($html, '<html')) {
            return $html;
        }

        $settings = get_option('tfm_cookie_consent_settings', array());

        if (empty($settings['prior_blocking'])) {
            return $this->rewrite_iframes($html, $settings);
        }

        $patterns = self::get_patterns();

        $html = preg_replace_callback(
            '#<script\b([^>]*)>(.*?)</script>#is',
            function ($m) use ($patterns) {
                $attrs = $m[1];
                $body  = $m[2];

                // Never touch our own inline blocks.
                if (false !== stripos($attrs, 'data-tfm-noblock')) {
                    return $m[0];
                }
                if (false !== stripos($attrs, 'data-tfm-consent')) {
                    return $m[0];
                }

                $category = false;
                if (preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $src)) {
                    $category = self::match_category($src[1], $patterns);
                }
                if (false === $category && '' !== trim($body)) {
                    $category = self::match_category($body, $patterns);
                }
                if (false === $category) {
                    return $m[0];
                }

                return self::neuter_script_tag('<script' . $attrs . '>', $category) . $body . '</script>';
            },
            $html
        );

        return $this->rewrite_iframes($html, $settings);
    }

    /**
     * Gate third-party iframes behind consent, if enabled.
     */
    private function rewrite_iframes($html, $settings) {
        if (!empty($settings['block_iframes'])) {
            $iframe_patterns = self::get_iframe_patterns();
            $html = preg_replace_callback(
                '#<iframe\b([^>]*)>#is',
                function ($m) use ($iframe_patterns) {
                    $attrs = $m[1];
                    if (false !== stripos($attrs, 'data-tfm-consent')) {
                        return $m[0];
                    }
                    if (!preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $src)) {
                        return $m[0];
                    }
                    $category = self::match_category($src[1], $iframe_patterns);
                    if (false === $category) {
                        return $m[0];
                    }
                    // Move src out of the way so nothing is requested.
                    $new = preg_replace(
                        '/\ssrc\s*=\s*(["\'])([^"\']+)\1/i',
                        ' data-tfm-src=$1$2$1',
                        $attrs,
                        1
                    );
                    return '<iframe' . $new . ' data-tfm-consent="' . esc_attr($category) . '">';
                },
                $html
            );
        }

        return $html;
    }

    /* ---------------------------------------------------------------------
     * Release
     * ------------------------------------------------------------------ */

    /**
     * Expose window.tfmConsentApply(categories) — pushes a Consent Mode update
     * and activates any tags whose category is now granted. frontend.js calls
     * this on save; it also runs once on load for stored consent.
     */
    public function output_release_runtime() {
        $settings = get_option('tfm_cookie_consent_settings', array());
        ?>
<script data-tfm-noblock="1">
(function () {
    var KEY = <?php echo wp_json_encode(self::STORAGE_KEY); ?>;

    function activate(category) {
        // Scripts
        var nodes = document.querySelectorAll('script[data-tfm-consent="' + category + '"]');
        for (var i = 0; i < nodes.length; i++) {
            var old = nodes[i];
            if (old.getAttribute('data-tfm-activated')) { continue; }
            var s = document.createElement('script');
            for (var a = 0; a < old.attributes.length; a++) {
                var at = old.attributes[a];
                if (at.name === 'type' || at.name === 'data-tfm-consent') { continue; }
                s.setAttribute(at.name, at.value);
            }
            if (!old.src) { s.text = old.text; }
            s.setAttribute('data-tfm-activated', '1');
            old.setAttribute('data-tfm-activated', '1');
            old.parentNode.insertBefore(s, old.nextSibling);
        }
        // Iframes
        var frames = document.querySelectorAll('iframe[data-tfm-consent="' + category + '"][data-tfm-src]');
        for (var f = 0; f < frames.length; f++) {
            frames[f].setAttribute('src', frames[f].getAttribute('data-tfm-src'));
            frames[f].removeAttribute('data-tfm-src');
        }
    }

    window.tfmConsentApply = function (categories) {
        categories = categories || {};
        var analytics  = !!categories.analytics;
        var marketing  = !!categories.marketing;
        var functional = !!categories.functional;

        if (window.tfmGpcActive) { marketing = false; }

        if (typeof gtag === 'function') {
            gtag('consent', 'update', {
                'ad_storage':              marketing  ? 'granted' : 'denied',
                'ad_user_data':            marketing  ? 'granted' : 'denied',
                'ad_personalization':      marketing  ? 'granted' : 'denied',
                'analytics_storage':       analytics  ? 'granted' : 'denied',
                'functionality_storage':   functional ? 'granted' : 'denied',
                'personalization_storage': functional ? 'granted' : 'denied',
                'security_storage':        'granted'
            });
        }

        if (analytics)  { activate('analytics'); }
        if (marketing)  { activate('marketing'); }
        if (functional) { activate('functional'); }
    };

    // Apply stored consent on load so returning visitors are not re-blocked.
    try {
        var raw = window.localStorage.getItem(KEY) || window.sessionStorage.getItem(KEY);
        if (raw) {
            var d = JSON.parse(raw);
            var fresh = d && d.timestamp && (!d.expires || Date.now() <= d.expires);
            if (fresh && d.categories) { window.tfmConsentApply(d.categories); }
        }
    } catch (e) {}
})();
</script>
        <?php
    }

    /**
     * [tfm_privacy_choices] — the "Your Privacy Choices" control US state laws
     * expect. Reopens the banner so a visitor can withdraw consent.
     */
    public function privacy_choices_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text'  => __('Your Privacy Choices', 'tfm-cookie-consent'),
            'class' => 'tfm-privacy-choices',
        ), $atts, 'tfm_privacy_choices');

        return sprintf(
            '<button type="button" class="%s" onclick="if(window.tfmCookieConsentReopen){window.tfmCookieConsentReopen();}">%s</button>',
            esc_attr($atts['class']),
            esc_html($atts['text'])
        );
    }
}
