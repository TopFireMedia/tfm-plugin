<?php
/**
 * TFM REST API Hardening
 *
 * WordPress exposes /wp-json/wp/v2/users to anonymous requests for any user who has
 * published a post of a public post type. That is core behaviour, not a
 * misconfiguration, and it discloses login usernames to anyone who asks — which turns
 * "guess the username and the password" into "guess the password".
 *
 * It is low severity on its own (no passwords, no email addresses, no write access),
 * but it is free to close and it is the kind of fleet-wide default that should be
 * handled here rather than per-site: a sample of live sites in October 2026 found the
 * endpoint open on some and closed on others, depending on which third-party security
 * plugin happened to be installed.
 *
 * Logged-in requests are left alone, so the block editor, author pickers and anything
 * else in wp-admin keep working. Anonymous callers get a plain 404 (rest_no_route),
 * which is the same response as a route that does not exist.
 *
 * Filterable via 'tfm_harden_rest_users' so an individual site can opt out if it ever
 * has a genuine need for anonymous author data (a headless front end, for example).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remove the core users routes for unauthenticated requests.
 *
 * @param array $endpoints Registered REST routes.
 * @return array
 */
function tfm_harden_rest_user_endpoints($endpoints) {
    if (is_user_logged_in()) {
        return $endpoints;
    }

    /**
     * Allow a site to keep the users endpoint public.
     *
     * @param bool $harden Whether to hide the users routes from anonymous callers.
     */
    if (!apply_filters('tfm_harden_rest_users', true)) {
        return $endpoints;
    }

    unset($endpoints['/wp/v2/users']);
    unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);

    return $endpoints;
}
add_filter('rest_endpoints', 'tfm_harden_rest_user_endpoints');
