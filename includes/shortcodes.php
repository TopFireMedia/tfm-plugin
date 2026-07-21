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
    // Phone shortcode
    function tfm_phone_shortcode() {
        $settings = tfm_load_settings();
        $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? ''); // Remove all non-numeric characters

        // Ensure it's exactly 10 digits
        if (strlen($raw_phone) !== 10) {
            return esc_html('000-000-0000'); // Default if invalid
        }

        // Get the selected format (default to format 4 for backward compatibility)
        $format = isset($settings['phone_format']) ? $settings['phone_format'] : '4';

        // Format based on selected option
        switch ($format) {
            case '1': // +1 (xxx) xxx-xxxx
                $formatted_phone = '+1 (' . substr($raw_phone, 0, 3) . ') ' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '2': // +1-xxx-xxx-xxxx
                $formatted_phone = '+1-' . substr($raw_phone, 0, 3) . '-' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '3': // (xxx) xxx-xxxx
                $formatted_phone = '(' . substr($raw_phone, 0, 3) . ') ' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '4': // xxx-xxx-xxxx
            default:
                $formatted_phone = substr($raw_phone, 0, 3) . '-' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
        }

        return esc_html($formatted_phone);
    }
    add_shortcode('phone', 'tfm_phone_shortcode');

    // Phone text link shortcode - formatted display with tel: link
    function tfm_phone_text_link_shortcode() {
        $settings = tfm_load_settings();
        $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? ''); // Remove all non-numeric characters

        // Ensure it's exactly 10 digits
        if (strlen($raw_phone) !== 10) {
            return esc_html('000-000-0000'); // Default if invalid
        }

        // Get the selected format (default to format 4 for backward compatibility)
        $format = isset($settings['phone_format']) ? $settings['phone_format'] : '4';

        // Format based on selected option
        switch ($format) {
            case '1': // +1 (xxx) xxx-xxxx
                $formatted_phone = '+1 (' . substr($raw_phone, 0, 3) . ') ' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '2': // +1-xxx-xxx-xxxx
                $formatted_phone = '+1-' . substr($raw_phone, 0, 3) . '-' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '3': // (xxx) xxx-xxxx
                $formatted_phone = '(' . substr($raw_phone, 0, 3) . ') ' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
            case '4': // xxx-xxx-xxxx
            default:
                $formatted_phone = substr($raw_phone, 0, 3) . '-' . substr($raw_phone, 3, 3) . '-' . substr($raw_phone, 6);
                break;
        }

        // Create tel: link with +1 prefix
        $tel_link = 'tel:+1' . $raw_phone;

        return '<a href="' . esc_attr($tel_link) . '">' . esc_html($formatted_phone) . '</a>';
    }
    add_shortcode('phone_text_link', 'tfm_phone_text_link_shortcode');

    // Phone link shortcode for Elementor compatibility
    function tfm_phone_link_shortcode() {
        $settings = tfm_load_settings();
        $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? ''); // Remove all non-numeric characters

        // Ensure it's exactly 10 digits and add +1 prefix for tel: links
        if (strlen($raw_phone) === 10) {
            $tel_phone = 'tel:+1' . $raw_phone; // Add tel: prefix and +1 for US numbers
            return esc_attr($tel_phone);
        } else {
            return 'tel:+10000000000'; // Default if invalid
        }
    }
    add_shortcode('phone_link', 'tfm_phone_link_shortcode');

    // Phone number shortcode for Elementor button links (complete tel: link)
    function tfm_phone_number_shortcode() {
        $settings = tfm_load_settings();
        $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? ''); // Remove all non-numeric characters

        // Ensure it's exactly 10 digits and add +1 prefix with tel: protocol
        if (strlen($raw_phone) === 10) {
            $tel_phone = 'tel:+1' . $raw_phone; // Complete tel: link
            return esc_attr($tel_phone);
        } else {
            return 'tel:+10000000000'; // Default if invalid
        }
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
