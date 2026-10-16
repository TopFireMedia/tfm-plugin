<?php
/**
 * Restore the main landmark, and with it the theme's skip link.
 *
 * Hello Elementor's header.php prints a skip link to `#content` on every request,
 * unconditionally. The element that carries that id - `<main id="content">` - only
 * exists inside the theme's own template parts (template-parts/single.php,
 * archive.php, search.php, 404.php). As soon as Elementor Pro's Theme Builder has
 * a template for the matching location, hello-elementor/index.php takes the other
 * branch:
 *
 *     if ( ! $is_elementor_theme_exist || ! elementor_theme_do_location( 'single' ) ) {
 *         get_template_part( 'template-parts/single' );
 *     }
 *
 * elementor_theme_do_location() returns true, the template part is never loaded,
 * and the page ships with no <main> at all and a skip link pointing at an id that
 * does not exist. Two separate axe failures, one cause:
 *
 * - landmark-one-main: "Document does not have a main landmark"
 * - skip-link: "Skip links are not focusable" (the target is simply absent)
 *
 * Every site built on Hello Elementor plus a Theme Builder single/archive template
 * has this, which is why it belongs here and not in a child theme: the child themes
 * are per-site and unevenly deployed, so a fix placed in one does not hold for the
 * fleet. Found on sal.tfmstaging.com during the 16 Oct 2026 QC pass, where no page
 * had a main landmark.
 *
 * ## Why these hooks
 *
 * Elementor Pro fires `elementor/theme/before_do_{$location}` and
 * `after_do_{$location}` from Locations_Manager::do_location(), and - importantly -
 * fires them only *after* the early return for an empty locations queue:
 *
 *     if ( empty( $this->locations_queue[ $location ] ) ) {
 *         return false;
 *     }
 *     do_action( "elementor/theme/before_do_{$location}", $this );
 *
 * So they fire exactly when Elementor is about to render the location, which is
 * exactly when the theme's own <main> has been skipped. When there is no Theme
 * Builder template, do_location() returns false before firing anything, the theme
 * falls through to its template part, and that part supplies the <main> itself.
 * That is what keeps this from doubling up.
 *
 * ## Why it is limited to Hello Elementor
 *
 * A theme that calls elementor_theme_do_location() from *inside* its own <main>
 * would end up with a nested landmark, which is worse than the problem being
 * fixed. Rather than guess, this targets the hello-elementor family the fleet is
 * standardised on. Any other theme can opt in:
 *
 *     add_filter( 'tfm_a11y_landmarks_supported_theme', '__return_true' );
 *
 * ## Why tabindex="-1"
 *
 * A skip link whose target is a non-interactive element moves the viewport but not
 * always focus - historically a Safari and Chrome bug, and still inconsistent.
 * tabindex="-1" makes <main> programmatically focusable so the next Tab continues
 * from the content rather than from the top of the page again. It does not add the
 * element to the tab order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the landmark fix should run on this site.
 *
 * @return bool
 */
function tfm_a11y_landmarks_enabled() {
	$settings = function_exists( 'tfm_load_settings' ) ? tfm_load_settings() : array();

	// On by default: a missing main landmark is a defect on every site that has it,
	// and the point of putting it here is that it holds without per-site setup.
	$enabled = ! array_key_exists( 'enable_a11y_landmarks', $settings )
		|| ! empty( $settings['enable_a11y_landmarks'] );

	if ( ! $enabled ) {
		return false;
	}

	return tfm_a11y_landmarks_supported_theme();
}

/**
 * Whether the active theme is one we know leaves no <main> behind.
 *
 * Checks the parent too, so hello-elementor-child counts.
 *
 * @return bool
 */
function tfm_a11y_landmarks_supported_theme() {
	$known = array( 'hello-elementor', 'hello-theme', 'hello-biz' );

	$supported = in_array( strtolower( get_template() ), $known, true )
		|| in_array( strtolower( get_stylesheet() ), $known, true );

	/** Let a site opt in a theme that is not in the list. */
	return (bool) apply_filters( 'tfm_a11y_landmarks_supported_theme', $supported );
}

/**
 * Tracks whether we have already opened a <main> this request.
 *
 * A single request can render more than one Elementor location - an archive
 * template that itself contains a loop, say - and two <main> elements is the same
 * axe failure in the other direction ("document has more than one main landmark").
 *
 * @param bool|null $set Pass true to mark it open, false to mark it closed.
 * @return bool Whether a <main> is currently open.
 */
function tfm_a11y_landmarks_state( $set = null ) {
	static $open = false;
	static $done = false;

	if ( true === $set ) {
		$open = true;
		$done = true;
	} elseif ( false === $set ) {
		$open = false;
	}

	return array( 'open' => $open, 'done' => $done );
}

/**
 * Open the main landmark before Elementor prints the location.
 */
function tfm_a11y_landmarks_open() {
	if ( ! tfm_a11y_landmarks_enabled() ) {
		return;
	}

	$state = tfm_a11y_landmarks_state();
	if ( $state['done'] ) {
		return; // one per request
	}

	// Deliberately no `site-main` class. Hello scopes its .site-main width rules to
	// `body:not([class*=elementor-page-])`, so the class is inert on an Elementor
	// page - but it is not inert on a non-Elementor one, where it would suddenly
	// start constraining the content width. The landmark does not need the class.
	$id = (string) apply_filters( 'tfm_a11y_landmarks_id', 'content' );

	tfm_a11y_landmarks_state( true );

	printf( '<main id="%s" tabindex="-1">', esc_attr( $id ) );
}

/**
 * Close it again once Elementor has finished.
 */
function tfm_a11y_landmarks_close() {
	$state = tfm_a11y_landmarks_state();
	if ( ! $state['open'] ) {
		return;
	}

	tfm_a11y_landmarks_state( false );

	echo '</main>';
}

/**
 * The content locations only. Header and footer are their own landmarks
 * (banner / contentinfo) and must stay outside main.
 */
foreach ( array( 'single', 'archive' ) as $tfm_a11y_location ) {
	add_action( "elementor/theme/before_do_{$tfm_a11y_location}", 'tfm_a11y_landmarks_open', 5 );
	add_action( "elementor/theme/after_do_{$tfm_a11y_location}", 'tfm_a11y_landmarks_close', 95 );
}
unset( $tfm_a11y_location );

/**
 * Elementor's own page templates, which are the other half of the problem.
 *
 * A page set to "Elementor Full Width" does not render through the theme's
 * index.php at all - Elementor swaps in
 * modules/page-templates/templates/header-footer.php, which calls get_header()
 * (so the skip link is still printed) and then prints the content directly:
 *
 *     get_header();
 *     do_action( 'elementor/page_templates/header-footer/before_content' );
 *     ...->print_content();
 *     do_action( 'elementor/page_templates/header-footer/after_content' );
 *     get_footer();
 *
 * No <main>, and elementor_theme_do_location() is never reached, so the hooks
 * above never fire. This is the common case rather than the exception: on
 * Salvation every page carried _wp_page_template=elementor_header_footer, which
 * is why the theme-location fix alone left the pages still failing while the
 * blog templates passed.
 *
 * Canvas is included for the same reason. It prints no header or footer, so there
 * is no skip link to repair, but the page still needs its one main landmark.
 */
foreach ( array( 'header-footer', 'canvas' ) as $tfm_a11y_template ) {
	add_action( "elementor/page_templates/{$tfm_a11y_template}/before_content", 'tfm_a11y_landmarks_open', 5 );
	add_action( "elementor/page_templates/{$tfm_a11y_template}/after_content", 'tfm_a11y_landmarks_close', 95 );
}
unset( $tfm_a11y_template );
