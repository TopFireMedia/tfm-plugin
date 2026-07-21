<?php
/**
 * TFM Front-end Scripts — enqueue, deferral, custom head/footer, accessibility widgets
 * Moved verbatim from topfiremedia.php during modularization — no logic change.
 */

if (!defined('ABSPATH')) {
    exit;
}

function tfm_insert_custom_scripts() {
    $settings = tfm_load_settings();
    
    // Add telephone meta tag
    echo '<meta name="format-detection" content="telephone=no">';
    
    // Add custom head scripts
    if (!empty($settings['custom_head_scripts'])) {
        // Handle scripts without tags
        $script_content = trim($settings['custom_head_scripts']);
        if (strpos($script_content, '<script') === false) {
            echo "<script>\n" . $script_content . "\n</script>\n";
        } else {
            echo $script_content . "\n";
        }
    }
}
add_action('wp_head', 'tfm_insert_custom_scripts', 999);

function tfm_insert_footer_scripts() {
    $settings = tfm_load_settings();
    
    // Add custom footer scripts
    if (!empty($settings['custom_footer_scripts'])) {
        // Handle scripts without tags
        $script_content = trim($settings['custom_footer_scripts']);
        if (strpos($script_content, '<script') === false) {
            echo "<script>\n" . $script_content . "\n</script>\n";
        } else {
            echo $script_content . "\n";
        }
    }
}
add_action('wp_footer', 'tfm_insert_footer_scripts', 999);

// Output accessiBe accessWidget script
function tfm_output_accessibe_script() {
    $settings = tfm_load_settings();

    if (empty($settings['enable_accessibe'])) {
        return;
    }

    $position_x    = in_array($settings['accessibe_position_x'] ?? 'right', ['left', 'right']) ? $settings['accessibe_position_x'] : 'right';
    $position_y    = in_array($settings['accessibe_position_y'] ?? 'bottom', ['top', 'center', 'bottom']) ? $settings['accessibe_position_y'] : 'bottom';
    $color         = !empty($settings['accessibe_color']) ? $settings['accessibe_color'] : '#146FF8';
    $language      = sanitize_key($settings['accessibe_language'] ?? 'en');
    $statement     = esc_url($settings['accessibe_statement_link'] ?? '');
    $hide_mobile   = !empty($settings['accessibe_hide_mobile']);
    $valid_icons   = ['checkmark','display','display2','display3','help','people','people2','settings','settings2','wheels','wheels2'];
    $trigger_icon  = in_array($settings['accessibe_trigger_icon'] ?? 'people', $valid_icons) ? $settings['accessibe_trigger_icon'] : 'people';
    $valid_sizes   = ['small', 'medium', 'big'];
    $trigger_size  = in_array($settings['accessibe_trigger_size'] ?? 'medium', $valid_sizes) ? $settings['accessibe_trigger_size'] : 'medium';
    $trigger_shape = ($settings['accessibe_trigger_shape'] ?? 'round') === 'square' ? '0' : '50%';

    $config = [
        'statementLink'    => $statement,
        'footerHtml'       => '',
        'hideMobile'       => $hide_mobile,
        'hideTrigger'      => false,
        'disableBgProcess' => false,
        'language'         => $language,
        'position'         => $position_x,
        'leadColor'        => $color,
        'triggerColor'     => $color,
        'triggerRadius'    => $trigger_shape,
        'triggerPositionX' => $position_x,
        'triggerPositionY' => $position_y,
        'triggerIcon'      => $trigger_icon,
        'triggerSize'      => $trigger_size,
        'triggerOffsetX'   => 20,
        'triggerOffsetY'   => 20,
        'mobile'           => [
            'triggerSize'      => 'small',
            'triggerPositionX' => $position_x,
            'triggerPositionY' => $position_y,
            'triggerOffsetX'   => 20,
            'triggerOffsetY'   => 20,
            'triggerRadius'    => $trigger_shape,
        ],
    ];

    $config_json = wp_json_encode($config);
    echo "<script>\n(function(){\n  var s = document.createElement('script');\n  var h = document.querySelector('head') || document.body;\n  s.src = 'https://acsbapp.com/apps/app/dist/js/app.js';\n  s.async = true;\n  s.onload = function(){ acsbJS.init(" . $config_json . "); };\n  h.appendChild(s);\n})();\n</script>\n";
}
add_action('wp_footer', 'tfm_output_accessibe_script', 5);
function tfm_enqueue_scripts() {
    $settings = tfm_load_settings();

    // Font Awesome — loads site-wide; can be turned off on sites that don't use
    // Font Awesome icons (default on to preserve existing behavior).
    if (!empty($settings['enable_font_awesome'])) {
        wp_enqueue_script('font-awesome', 'https://kit.fontawesome.com/79c9dcfe2d.js', [], null, true);
    }

    // Only register the deferral filter when deferral is actually enabled —
    // otherwise tfm_defer_scripts() runs (and loaded settings) for every single
    // <script> tag on the page for no reason.
    if (!empty($settings['defer_scripts']) && !has_filter('script_loader_tag', 'tfm_defer_scripts')) {
        add_filter('script_loader_tag', 'tfm_defer_scripts', 10, 2);
    }

    // Phone formatter — only needed on pages with phone input fields; can be
    // turned off where forms aren't used (default on to preserve behavior).
    if (!empty($settings['enable_phone_formatter'])) {
        wp_enqueue_script(
            'tfm-phone-formatter',
            plugin_dir_url(__FILE__) . 'assets/js/phone-formatter.js',
            [],
            '1.0.0',
            true
        );
    }

    // Add UserWay widget if enabled
    if ($settings['enable_userway'] && !empty($settings['userway_account_id'])) {
        // Map position values to UserWay's numeric values according to documentation
        $position_map = [
            'top_right' => '1',
            'middle_right' => '2',
            'bottom_right' => '3',
            'bottom_middle' => '4',
            'bottom_left' => '5',
            'middle_left' => '6',
            'top_left' => '7',
            'top_middle' => '8'
        ];
        
        $mobile_position_map = [
            'default' => '0',
            'top_right' => '1',
            'middle_right' => '2',
            'bottom_right' => '3',
            'bottom_middle' => '4',
            'bottom_left' => '5',
            'middle_left' => '6',
            'top_left' => '7',
            'top_middle' => '8'
        ];
        
        $position = isset($position_map[$settings['userway_position']]) ? $position_map[$settings['userway_position']] : '3'; // Default to bottom_right
        $mobile_position = isset($mobile_position_map[$settings['userway_mobile_position']]) ? $mobile_position_map[$settings['userway_mobile_position']] : '0';
        
        // Register the script with data attributes
        wp_register_script(
            'userway-widget',
            'https://cdn.userway.org/widget.js',
            [],
            null,
            true
        );
        
        // Add data attributes to the script tag
        add_filter('script_loader_tag', function($tag, $handle) use ($settings, $position, $mobile_position) {
            if ($handle === 'userway-widget') {
                $tag = str_replace(
                    '></script>',
                    sprintf(
                        ' data-account="%s" data-position="%s" data-mobile-position="%s" data-color="%s"></script>',
                        esc_attr($settings['userway_account_id']),
                        esc_attr($position),
                        esc_attr($mobile_position),
                        esc_attr($settings['userway_color'])
                    ),
                    $tag
                );
            }
            return $tag;
        }, 10, 2);
        
        wp_enqueue_script('userway-widget');
    }

    // Add WhatConverts tracking if enabled
    if ($settings['enable_whatconverts'] && !empty($settings['whatconverts_account_id'])) {
        // Add the initialization code
        wp_add_inline_script('jquery', 'var $wc_load=function(a){return JSON.parse(JSON.stringify(a))},$wc_leads=$wc_leads||{doc:{url:$wc_load(document.URL),ref:$wc_load(document.referrer),search:$wc_load(location.search),hash:$wc_load(location.hash)}};', 'before');
        
        // Add the main tracking script
        $account_id = esc_js($settings['whatconverts_account_id']);
        wp_enqueue_script('whatconverts', "//s.ksrndkehqnwntyxlhgto.com/{$account_id}.js", ['jquery'], null, true);
    }

    // Enqueue video defer styles
    wp_enqueue_style(
        'tfm-video-defer',
        TFM_PLUGIN_URL . 'assets/css/video-defer.css',
        [],
        TFM_PLUGIN_VERSION
    );
}
add_action('wp_enqueue_scripts', 'tfm_enqueue_scripts');

// Function to defer scripts
function tfm_defer_scripts($tag, $handle) {
    $settings = tfm_load_settings();
    if (empty($settings['defer_scripts'])) return $tag;

    $allowlist = array_filter(array_map('trim', explode(',', $settings['defer_handles'] ?? '')));
    if (!empty($allowlist) && in_array($handle, $allowlist, true)) {
        // Defer only external scripts with src
        if (strpos($tag, ' src=') !== false) {
            return str_replace(' src', ' defer src', $tag);
        }
    }
    return $tag;
}
