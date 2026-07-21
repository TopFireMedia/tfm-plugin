<?php
/**
 * TFM SVG Uploads
 * Gates SVG uploads to unfiltered_html users and sanitizes every uploaded SVG
 * (see class-tfm-svg-sanitizer.php). Moved verbatim from topfiremedia.php during
 * modularization — no logic change.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Allow SVG uploads if enabled — only for users who can already post unfiltered
// HTML (admins / super admins). This prevents lower-privilege users from
// uploading a scripted SVG that would execute in an admin's browser (stored XSS).
function tfm_allow_svg_uploads($mimes) {
    $settings = tfm_load_settings();
    if (!empty($settings['enable_svg_uploads']) && current_user_can('unfiltered_html')) {
        // Only plain .svg — .svgz (gzipped) can't be DOM-sanitized without
        // gunzipping, so it's not accepted rather than shipped as a dead path.
        $mimes['svg'] = 'image/svg+xml';
    }
    return $mimes;
}
add_filter('upload_mimes', 'tfm_allow_svg_uploads');

// WordPress's real-mime check (finfo) reports SVGs as text/plain or image/svg,
// which fails the upload. When SVG uploads are permitted for this user, accept
// the .svg extension so legitimate files can be stored.
function tfm_fix_svg_filetype($data, $file, $filename, $mimes, $real_mime = '') {
    $settings = tfm_load_settings();
    if (empty($settings['enable_svg_uploads']) || !current_user_can('unfiltered_html')) {
        return $data;
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'svg') {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'tfm_fix_svg_filetype', 10, 5);

// Sanitize every SVG before it is stored — strip scripts, event handlers,
// external entities, and script URIs. Reject the upload if it can't be made safe.
function tfm_sanitize_svg_on_upload($upload) {
    if (!isset($upload['type'], $upload['file']) || $upload['type'] !== 'image/svg+xml') {
        return $upload;
    }

    $contents = file_get_contents($upload['file']);
    // Fail closed: if we can't read it, we can't prove it's safe.
    if ($contents === false) {
        @unlink($upload['file']);
        return ['error' => __('This SVG could not be read for sanitization and was not uploaded.', 'topfiremedia')];
    }

    $clean = TFM_SVG_Sanitizer::sanitize($contents);
    if ($clean === false) {
        @unlink($upload['file']);
        return ['error' => __('This SVG could not be processed safely and was not uploaded.', 'topfiremedia')];
    }

    if (file_put_contents($upload['file'], $clean) === false) {
        @unlink($upload['file']);
        return ['error' => __('This SVG could not be saved safely and was not uploaded.', 'topfiremedia')];
    }
    return $upload;
}
add_filter('wp_handle_upload', 'tfm_sanitize_svg_on_upload');
add_filter('wp_handle_upload_prefilter', function ($file) {
    // Prefilter runs before the type is finalized; sanitize by extension here too.
    if (empty($file['name']) || !preg_match('/\.svg$/i', $file['name']) || empty($file['tmp_name'])) {
        return $file;
    }

    $settings = tfm_load_settings();
    if (empty($settings['enable_svg_uploads']) || !current_user_can('unfiltered_html')) {
        $file['error'] = __('SVG uploads are not permitted for your account.', 'topfiremedia');
        return $file;
    }

    $contents = file_get_contents($file['tmp_name']);
    if ($contents === false) {
        $file['error'] = __('This SVG could not be read for sanitization and was not uploaded.', 'topfiremedia');
        return $file;
    }

    $clean = TFM_SVG_Sanitizer::sanitize($contents);
    if ($clean === false) {
        $file['error'] = __('This SVG could not be processed safely and was not uploaded.', 'topfiremedia');
        return $file;
    }

    if (file_put_contents($file['tmp_name'], $clean) === false) {
        $file['error'] = __('This SVG could not be saved safely and was not uploaded.', 'topfiremedia');
    }
    return $file;
});
