<?php
/**
 * Disable WordPress speculative loading.
 *
 * WordPress 6.8 began printing a Speculation Rules block into every page head:
 *
 *     <script type="speculationrules">
 *     {"prefetch":[{"source":"document","where":{"and":[
 *       {"href_matches":"/*"},
 *       {"not":{"href_matches":["/wp-*.php","/wp-admin/*","/wp-content/uploads/*", ...]}}
 *     ]}}]}
 *     </script>
 *
 * It tells the browser which links are safe to prefetch. The strings inside it are
 * URL *patterns*, not URLs — but Googlebot reads them as URLs and tries to crawl
 * them, which is where this stops being harmless.
 *
 * ## The evidence
 *
 * Google Search Console emailed salvationwellnessfranchise.com on 16 Sep 2026 with
 * "New reasons prevent pages from being indexed". Every URL in the "Not found (404)"
 * list was a literal pattern string lifted out of that block — seven of seven:
 *
 *     /*                                              /wp-content/plugins/*
 *     /wp-*.php                                       /wp-content/themes/hello-elementor/*
 *     /wp-content/*                                   /wp-content/themes/hello-elementor-child/*
 *     /wp-content/uploads/*
 *
 * The eighth pattern, `/wp-admin/*`, landed under "Blocked by robots.txt" instead,
 * because robots.txt disallows that path — same artifact, different bucket. The list
 * even named the site's theme, which is what made it look site-specific rather than
 * like a WordPress-wide behaviour.
 *
 * Nothing was broken. The sitemap was clean, internal links were clean, and real
 * pages were indexing normally. Google was failing to fetch URLs that had never
 * existed and were never linked.
 *
 * ## Why this belongs in the plugin
 *
 * Every WordPress site on the fleet emits this block and will generate the same
 * alert. Checked on 16 Sep 2026: salvationwellnessfranchise.com plus the ivy, sal,
 * tgp, lil, ppb and phd staging installs — all seven, all on WP 7.1, all identical.
 * Fixing it per site means fixing it thirty times, or explaining the same false
 * alarm to thirty clients.
 *
 * ## The trade-off, stated plainly
 *
 * Speculative loading is a real if modest performance feature — prefetching on
 * hover makes the next page feel instant. Turning it off gives that up to silence
 * a Search Console report that is cosmetic. That is the right trade when the
 * alerts are reaching clients (they generate "your site is broken" emails about a
 * site that is not broken), and the wrong one if nobody is looking at GSC.
 *
 * So it is a setting, defaulting to on, and either lever turns it back:
 *
 *     // Per site, in code:
 *     add_filter( 'tfm_disable_speculative_loading', '__return_false' );
 *
 * or clear `disable_speculative_loading` in the TFM plugin settings.
 *
 * @since 3.47.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Should speculative loading be switched off on this site?
 *
 * Defaults to true when the setting is absent, so a site that has never saved the
 * plugin's settings still gets the fix — which is the point of shipping it here
 * rather than toggling thirty installs by hand.
 *
 * @return bool
 */
function tfm_speculative_loading_is_disabled() {
	$settings = function_exists( 'tfm_load_settings' ) ? tfm_load_settings() : array();

	$disabled = ! array_key_exists( 'disable_speculative_loading', $settings )
		|| ! empty( $settings['disable_speculative_loading'] );

	/**
	 * Override per site. Return false to let WordPress print its speculation rules.
	 *
	 * @param bool $disabled Whether to disable speculative loading.
	 */
	return (bool) apply_filters( 'tfm_disable_speculative_loading', $disabled );
}

/**
 * Remove the speculation rules configuration.
 *
 * `wp_speculation_rules_configuration` is WordPress's own switch (6.8+): returning
 * null disables speculative loading entirely, so no block is printed and there are
 * no pattern strings for a crawler to mistake for URLs.
 *
 * Deliberately filtered rather than unhooking `wp_print_speculation_rules` — the
 * filter is the documented, supported entry point, and it keeps working if core
 * renames or re-registers the print action.
 *
 * Runs late (priority 99) so a site-specific filter added at the default priority
 * still wins if it wants the feature back.
 *
 * @param array|null $config Speculation rules configuration, or null if already off.
 * @return array|null
 */
function tfm_speculative_loading_configuration( $config ) {
	return tfm_speculative_loading_is_disabled() ? null : $config;
}
add_filter( 'wp_speculation_rules_configuration', 'tfm_speculative_loading_configuration', 99 );
