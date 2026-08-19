<?php
/**
 * TFM Shortcodes — all plugin shortcodes (gated by enable_shortcodes)
 * Moved verbatim from topfiremedia.php during modularization — no logic change.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Shortcodes
if (tfm_load_settings()['enable_shortcodes']) {
    // Basic shortcodes
    function tfm_year_shortcode() { return esc_html(date_i18n('Y')); }
    function tfm_site_title_shortcode() { return esc_html(get_bloginfo('name')); }
    function tfm_page_title_shortcode() { return esc_html(get_the_title()); }

    add_shortcode('year', 'tfm_year_shortcode');
    add_shortcode('site_title', 'tfm_site_title_shortcode');
    add_shortcode('page_title', 'tfm_page_title_shortcode');
    /**
     * Phone numbers, including vanity (alphanumeric) ones.
     *
     * A client may set the number as a vanity string such as "84 FreeLYFE".
     * Those have to DISPLAY as written but DIAL the real digits, so letters are
     * translated with the standard phone keypad (ABC=2 … WXYZ=9) for the tel:
     * link only. Letters are never left in an href — dialers do not handle them
     * consistently, and iOS in particular will refuse the number outright.
     *
     * Plain numeric entries are unchanged: they are still reformatted by the
     * site's phone_format setting exactly as before, so existing sites are
     * unaffected by this.
     */
    function tfm_phone_keypad_digits($value) {
        static $map = array(
            'A' => '2', 'B' => '2', 'C' => '2',  'D' => '3', 'E' => '3', 'F' => '3',
            'G' => '4', 'H' => '4', 'I' => '4',  'J' => '5', 'K' => '5', 'L' => '5',
            'M' => '6', 'N' => '6', 'O' => '6',  'P' => '7', 'Q' => '7', 'R' => '7', 'S' => '7',
            'T' => '8', 'U' => '8', 'V' => '8',  'W' => '9', 'X' => '9', 'Y' => '9', 'Z' => '9',
        );

        $out = '';
        foreach (str_split(strtoupper((string) $value)) as $ch) {
            if (ctype_digit($ch)) {
                $out .= $ch;
            } elseif (isset($map[$ch])) {
                $out .= $map[$ch];
            }
        }

        // Tolerate a country code being included in the setting; the rest of the
        // plugin works in 10-digit US numbers and adds the +1 itself.
        if (11 === strlen($out) && '1' === $out[0]) {
            $out = substr($out, 1);
        }

        return $out;
    }

    /** A vanity number is any value containing letters. */
    function tfm_phone_is_vanity($value) {
        return (bool) preg_match('/[A-Za-z]/', (string) $value);
    }

    /**
     * The visible string. A vanity number is shown exactly as the client wrote
     * it — reformatting into xxx-xxx-xxxx would destroy the word, which is the
     * entire point of having one.
     */
    function tfm_phone_display($value, $format) {
        $d = tfm_phone_keypad_digits($value);

        // Show it verbatim only when the letters actually spell a valid number.
        // Without the length check, stray text in the setting ("call us!") would
        // render as-is with a dead tel: link, which looks deliberate and is worse
        // than an obvious placeholder.
        if (tfm_phone_is_vanity($value) && 10 === strlen($d)) {
            return trim(preg_replace('/\s+/', ' ', (string) $value));
        }

        if (10 !== strlen($d)) {
            return '000-000-0000';
        }

        switch ($format) {
            case '1': // +1 (xxx) xxx-xxxx
                return '+1 (' . substr($d, 0, 3) . ') ' . substr($d, 3, 3) . '-' . substr($d, 6);
            case '2': // +1-xxx-xxx-xxxx
                return '+1-' . substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6);
            case '3': // (xxx) xxx-xxxx
                return '(' . substr($d, 0, 3) . ') ' . substr($d, 3, 3) . '-' . substr($d, 6);
            case '5': // xxx.xxx.xxxx
                return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6);
            case '4': // xxx-xxx-xxxx
            default:
                return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6);
        }
    }

    /** The tel: href — always real digits. */
    function tfm_phone_tel_link($value) {
        $d = tfm_phone_keypad_digits($value);
        return (10 === strlen($d)) ? 'tel:+1' . $d : 'tel:+10000000000';
    }

    function tfm_phone_shortcode() {
        $settings = tfm_load_settings();
        $format   = isset($settings['phone_format']) ? $settings['phone_format'] : '4';
        return esc_html(tfm_phone_display($settings['phone'] ?? '', $format));
    }
    add_shortcode('phone', 'tfm_phone_shortcode');

    // Formatted display wrapped in a tel: link.
    function tfm_phone_text_link_shortcode() {
        $settings = tfm_load_settings();
        $format   = isset($settings['phone_format']) ? $settings['phone_format'] : '4';
        $display  = tfm_phone_display($settings['phone'] ?? '', $format);
        $tel      = tfm_phone_tel_link($settings['phone'] ?? '');

        // Kept on one line: a vanity number split across two lines is unreadable,
        // and breaking mid-word makes it look like a typo rather than a number.
        return '<a href="' . esc_attr($tel) . '" class="tfm-phone-link" style="white-space:nowrap">'
            . esc_html($display) . '</a>';
    }
    add_shortcode('phone_text_link', 'tfm_phone_text_link_shortcode');

    // Bare tel: value, for Elementor link fields.
    function tfm_phone_link_shortcode() {
        $settings = tfm_load_settings();
        return esc_attr(tfm_phone_tel_link($settings['phone'] ?? ''));
    }
    add_shortcode('phone_link', 'tfm_phone_link_shortcode');

    // Same as phone_link; kept as a separate shortcode because sites use both.
    function tfm_phone_number_shortcode() {
        $settings = tfm_load_settings();
        return esc_attr(tfm_phone_tel_link($settings['phone'] ?? ''));
    }
    add_shortcode('phone_number', 'tfm_phone_number_shortcode');

    // HTML Sitemap shortcode
    function tfm_sitemap_shortcode($atts) {
        $atts = shortcode_atts([
            'post_types' => '',
            'show_dates' => '',
            'show_counts' => '',
            'exclude_empty_cats' => ''
        ], $atts);

        // Convert string attributes to appropriate types
        $args = [];
        if (!empty($atts['post_types'])) {
            $args['post_types'] = $atts['post_types'];
        }
        if ($atts['show_dates'] !== '') {
            $args['show_dates'] = $atts['show_dates'];
        }
        if ($atts['show_counts'] !== '') {
            $args['show_counts'] = $atts['show_counts'];
        }
        if ($atts['exclude_empty_cats'] !== '') {
            $args['exclude_empty_cats'] = $atts['exclude_empty_cats'];
        }

        // Prevent wpautop from being applied to this shortcode output
        $output = tfm_sitemap_generate($args);
        return $output;
    }
    add_shortcode('tfm_sitemap', 'tfm_sitemap_shortcode');

// Create a global variable that Elementor can access — only when a valid phone
// is configured (no point printing a placeholder script on every page otherwise).
add_action('wp_head', function() {
    $settings = tfm_load_settings();
    $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? '');

    if (strlen($raw_phone) !== 10) {
        return;
    }

    echo '<script>window.tfmPhoneNumber = "' . esc_js('+1' . $raw_phone) . '";</script>';
});


    // Franchisee Financial Shortcodes
    function tfm_estimated_initial_investment_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['estimated_initial_investment']) ? 
                  esc_html($settings['franchisee_financials']['estimated_initial_investment']) : 'Not specified';
        return $amount;
    }
    add_shortcode('estimated_initial_investment', 'tfm_estimated_initial_investment_shortcode');

    function tfm_minimum_liquid_capital_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['minimum_liquid_capital']) ? 
                  esc_html($settings['franchisee_financials']['minimum_liquid_capital']) : 'Not specified';
        return $amount;
    }
    add_shortcode('minimum_liquid_capital', 'tfm_minimum_liquid_capital_shortcode');

    function tfm_franchise_fee_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['franchise_fee']) ? 
                  esc_html($settings['franchisee_financials']['franchise_fee']) : 'Not specified';
        return $amount;
    }
    add_shortcode('franchise_fee', 'tfm_franchise_fee_shortcode');

    function tfm_net_worth_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['net_worth']) ? 
                  esc_html($settings['franchisee_financials']['net_worth']) : 'Not specified';
        return $amount;
    }
    add_shortcode('net_worth', 'tfm_net_worth_shortcode');

    function tfm_average_unit_volume_shortcode() {
        $settings = tfm_load_settings();
        $amount = !empty($settings['franchisee_financials']['average_unit_volume']) ? 
                  esc_html($settings['franchisee_financials']['average_unit_volume']) : 'Not specified';
        return $amount;
    }
    add_shortcode('average_unit_volume', 'tfm_average_unit_volume_shortcode');

    // Full Address Shortcode
    function tfm_full_address_shortcode() {
        $settings = tfm_load_settings();
        if (empty($settings['full_address'])) {
            return '';
        }
        
        $address = $settings['full_address'];
        // Convert line breaks to <br> tags for proper display
        $address = wp_kses_post($address);
        $address = nl2br($address);
        
        return $address;
    }
    add_shortcode('full_address', 'tfm_full_address_shortcode');

    // Email shortcode
    function tfm_email_shortcode() {
        $settings = tfm_load_settings();
        $email = !empty($settings['email']) ? sanitize_email($settings['email']) : 'info@example.com';

        return '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
    }
    add_shortcode('email', 'tfm_email_shortcode');

    // Lead Magnet shortcodes
    function tfm_lead_magnet_image_shortcode($atts = []) {
        $settings = tfm_load_settings();
        $image_id = absint($settings['lead_magnet']['image_id'] ?? 0);
        if (!$image_id) return '';
        $atts = shortcode_atts([
            'size'  => 'large',
            'class' => 'tfm-lead-magnet-image',
            'alt'   => 'Industry Outlook',
        ], $atts, 'lead_magnet_image');
        $img = wp_get_attachment_image($image_id, $atts['size'], false, ['class' => $atts['class'], 'alt' => $atts['alt']]);
        return $img ?: '';
    }
    add_shortcode('lead_magnet_image', 'tfm_lead_magnet_image_shortcode');

    function tfm_lead_magnet_link_shortcode($atts = []) {
        $settings = tfm_load_settings();
        $file_id = absint($settings['lead_magnet']['file_id'] ?? 0);
        if (!$file_id) return '';
        $atts = shortcode_atts([
            'text' => 'Download Industry Outlook',
            'class' => 'tfm-lead-magnet-link',
            'target' => '_blank',
            'rel' => 'noopener',
        ], $atts, 'lead_magnet_link');
        $url = wp_get_attachment_url($file_id);
        if (!$url) return '';
        $link = sprintf('<a href="%s" class="%s" target="%s" rel="%s">%s</a>', esc_url($url), esc_attr($atts['class']), esc_attr($atts['target']), esc_attr($atts['rel']), esc_html($atts['text']));
        return $link;
    }
    add_shortcode('lead_magnet_link', 'tfm_lead_magnet_link_shortcode');

    // Lead Magnet URL shortcode for Elementor compatibility
    function tfm_lead_magnet_url_shortcode() {
        $settings = tfm_load_settings();
        $file_id = absint($settings['lead_magnet']['file_id'] ?? 0);
        if (!$file_id) return '';
        $url = wp_get_attachment_url($file_id);
        return $url ? esc_url($url) : '';
    }
    add_shortcode('lead_magnet_url', 'tfm_lead_magnet_url_shortcode');
}
