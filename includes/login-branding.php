<?php
/**
 * TFM Login Branding
 * Custom logo, URL, and title on the wp-login screen.
 *
 * (Moved verbatim from topfiremedia.php during modularization — no logic change.)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function wpb_login_logo() {
    $settings = tfm_load_settings();
    $logo_url = isset($settings['login_logo_url']) ? esc_url($settings['login_logo_url']) : '';
    if (empty($logo_url)) return; ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url("<?php echo $logo_url; ?>");
            height: 80px;
            width: 80px;
            background-size: 80px 80px;
            background-repeat: no-repeat;
            padding-bottom: 10px;
        }
    </style>
<?php }
add_action('login_enqueue_scripts', 'wpb_login_logo');

function my_login_logo_url() {
    return home_url();
}
add_filter('login_headerurl', 'my_login_logo_url');

function my_login_logo_url_title() {
    return 'Your Site Name and Info';
}
add_filter('login_headertext', 'my_login_logo_url_title');
