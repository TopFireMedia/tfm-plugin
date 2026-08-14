<?php
/**
 * Remove the default themes WordPress ships with.
 *
 * Unused themes are dormant attack surface — they sit unpatched unless someone
 * remembers to update themes they never look at — and they were repeatedly missed
 * during final QC (raised in the 8/10 meeting). A manual purge does not hold:
 * a WordPress core update installs the newest default theme again, so the sweep
 * has to recur. That is why this lives in the plugin rather than being a one-off
 * pass over the fleet.
 *
 * ## What counts as a default theme
 *
 * Only WordPress's own bundled themes — the `twenty*` family. Deliberately a
 * fixed list rather than a `^twenty` pattern match: a client theme legitimately
 * named "twentyfour-seven" would otherwise be deleted, and that failure mode is
 * silent and unrecoverable without a backup.
 *
 * ## Guards
 *
 * Deleting the wrong theme takes a site down, so every deletion is checked
 * against all of these first:
 *
 * - never the active theme;
 * - never the parent of an active child theme (deleting the parent of, say,
 *   hello-elementor-child leaves the child with nothing to inherit);
 * - never the last remaining theme;
 * - never a theme allow-listed via the `tfm_theme_cleanup_keep` filter;
 * - on multisite, never a network-enabled theme, which another site may be using.
 *
 * These matter concretely here: nine `red*.tfmstaging.com` installs run
 * twentytwentyfive as their *active* theme, so a naive sweep would break them.
 *
 * ## The fallback trade-off
 *
 * WordPress falls back to a default theme when the active one fatals. Removing
 * every default removes that safety net: a fatal in the active theme becomes a
 * hard failure rather than an ugly-but-rendering page. We accept that — since
 * WP 5.2 recovery mode emails an admin and keeps wp-admin reachable, and for a
 * client site an unstyled fallback is arguably worse than an outage you are told
 * about. To keep one anyway on a given site:
 *
 *     add_filter( 'tfm_theme_cleanup_keep', function ( $keep ) {
 *         $keep[] = 'twentytwentyfive';
 *         return $keep;
 *     } );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress's bundled themes, oldest first. Extend as new ones ship — an unknown
 * default is simply left alone, which is the safe direction to fail in.
 */
function tfm_theme_cleanup_default_slugs() {
	return apply_filters(
		'tfm_theme_cleanup_defaults',
		array(
			'twentyten', 'twentyeleven', 'twentytwelve', 'twentythirteen',
			'twentyfourteen', 'twentyfifteen', 'twentysixteen', 'twentyseventeen',
			'twentynineteen', 'twentytwenty', 'twentytwentyone', 'twentytwentytwo',
			'twentytwentythree', 'twentytwentyfour', 'twentytwentyfive',
			'twentytwentysix',
		)
	);
}

/**
 * Themes that must never be deleted on this site, whatever else is true.
 *
 * @return string[] Lowercased stylesheet directories.
 */
function tfm_theme_cleanup_protected() {
	$protected = array(
		get_stylesheet(), // active theme
		get_template(),   // its parent, when the active theme is a child
	);

	if ( is_multisite() ) {
		// A theme enabled network-wide may be active on a site we are not looking at.
		$allowed = get_site_option( 'allowedthemes', array() );
		if ( is_array( $allowed ) ) {
			$protected = array_merge( $protected, array_keys( $allowed ) );
		}
	}

	/** Allow a site to keep specific themes — e.g. one default as a fallback. */
	$protected = apply_filters( 'tfm_theme_cleanup_keep', $protected );

	return array_unique( array_map( 'strtolower', array_filter( (array) $protected ) ) );
}

/**
 * Work out which default themes are safe to delete, without deleting anything.
 *
 * Separated from the deletion so it can be reported (heartbeat, admin notice)
 * and unit-reasoned about without side effects.
 *
 * @return string[] Stylesheet directories that would be removed.
 */
function tfm_theme_cleanup_removable() {
	if ( ! function_exists( 'wp_get_themes' ) ) {
		return array();
	}

	$installed = wp_get_themes();
	$defaults  = array_map( 'strtolower', tfm_theme_cleanup_default_slugs() );
	$protected = tfm_theme_cleanup_protected();
	$removable = array();

	foreach ( $installed as $stylesheet => $theme ) {
		$slug = strtolower( $stylesheet );

		if ( ! in_array( $slug, $defaults, true ) ) {
			continue; // not a WordPress default — leave it alone
		}
		if ( in_array( $slug, $protected, true ) ) {
			continue;
		}
		$removable[] = $stylesheet;
	}

	// Never strip the site down to nothing, even if every installed theme is a default.
	if ( count( $removable ) >= count( $installed ) ) {
		return array();
	}

	return $removable;
}

/**
 * Delete the removable default themes.
 *
 * @return array{deleted: string[], failed: string[]}
 */
function tfm_theme_cleanup_run() {
	$result = array( 'deleted' => array(), 'failed' => array() );

	$removable = tfm_theme_cleanup_removable();
	if ( empty( $removable ) ) {
		return $result;
	}

	// delete_theme() lives in an admin include that is not loaded on cron requests.
	if ( ! function_exists( 'delete_theme' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
	}
	if ( ! function_exists( 'delete_theme' ) ) {
		return $result;
	}

	// delete_theme() uses the filesystem abstraction; without credentials in place
	// it returns a form request rather than deleting, so establish direct access first.
	if ( ! WP_Filesystem() ) {
		return $result;
	}

	foreach ( $removable as $stylesheet ) {
		$deleted = delete_theme( $stylesheet );
		if ( true === $deleted ) {
			$result['deleted'][] = $stylesheet;
		} else {
			$result['failed'][] = $stylesheet;
		}
	}

	if ( ! empty( $result['deleted'] ) ) {
		do_action( 'tfm_theme_cleanup_deleted', $result['deleted'] );
		error_log(
			'[TFM] theme-cleanup removed default theme(s): ' . implode( ', ', $result['deleted'] )
		);
	}

	return $result;
}

/**
 * Daily schedule. Recurring rather than one-shot because core updates reinstall
 * the newest default theme, which is how they kept reappearing.
 */
add_action( 'init', function () {
	$settings = function_exists( 'tfm_load_settings' ) ? tfm_load_settings() : array();
	$enabled  = ! empty( $settings['enable_theme_cleanup'] );

	if ( ! $enabled ) {
		// Setting turned off after being on: stop the schedule rather than leave it orphaned.
		$next = wp_next_scheduled( 'tfm_theme_cleanup_event' );
		if ( $next ) {
			wp_unschedule_event( $next, 'tfm_theme_cleanup_event' );
		}
		return;
	}

	if ( ! wp_next_scheduled( 'tfm_theme_cleanup_event' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'tfm_theme_cleanup_event' );
	}
} );

add_action( 'tfm_theme_cleanup_event', 'tfm_theme_cleanup_run' );

/**
 * Also sweep immediately after a core update, which is the moment a new default
 * theme appears. Waiting for the next daily run would leave it installed for up
 * to a day — and, more to the point, visible during a QC pass.
 */
add_action( '_core_updated_successfully', function () {
	$settings = function_exists( 'tfm_load_settings' ) ? tfm_load_settings() : array();
	if ( ! empty( $settings['enable_theme_cleanup'] ) ) {
		tfm_theme_cleanup_run();
	}
} );
