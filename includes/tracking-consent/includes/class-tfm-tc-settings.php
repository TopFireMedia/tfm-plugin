<?php
/**
 * Settings storage, defaults and sanitization.
 *
 * @package TFM_Tracking_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central settings handler. All options live in a single autoloaded
 * array to keep the frontend to one option query.
 */
class TFM_TC_Settings {

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// General.
			'enabled'             => 1,
			'blocking_enabled'    => 1,
			'block_iframes'       => 1,
			'respect_gpc'         => 1,
			'cookie_expiry_days'  => 365,
			'consent_version'     => 1,
			'logging_enabled'     => 0,
			'delete_on_uninstall' => 0,

			// Google.
			'consent_mode'        => 1,
			'ads_data_redaction'  => 1,
			// Keeps ad click IDs in the URL while storage is denied so
			// conversion measurement degrades rather than disappearing. Off by
			// default because it rewrites outbound links.
			'url_passthrough'     => 0,
			// Comma-separated ISO country codes limiting the denied default to
			// those regions. Empty = denied-by-default worldwide, which is the
			// safer choice under US state privacy laws.
			'consent_mode_region' => '',
			'gtm_id'              => '',
			'gtm_mode'            => 'blocked', // blocked | consent_mode.

			// Services / patterns.
			'disabled_services'   => array(),
			'custom_patterns'     => '',

			// Banner content.
			'heading'             => __( 'We value your privacy', 'tfm-tracking-consent' ),
			'message'             => __( 'We use cookies and similar technologies to operate this site, analyze traffic, and support our marketing. Non-essential cookies are set only with your consent. You can accept all, reject all, or manage your preferences by category.', 'tfm-tracking-consent' ),
			'privacy_url'         => '',
			'btn_accept'          => __( 'Accept All', 'tfm-tracking-consent' ),
			'btn_reject'          => __( 'Reject All', 'tfm-tracking-consent' ),
			'btn_prefs'           => __( 'Cookie Settings', 'tfm-tracking-consent' ),
			'btn_save'            => __( 'Save Preferences', 'tfm-tracking-consent' ),

			// Category descriptions (shown in the preferences panel).
			'cat_desc_necessary'       => __( 'Required for the website to function (security, load balancing, consent storage). Always active.', 'tfm-tracking-consent' ),
			'cat_desc_functional'      => __( 'Enable enhanced functionality such as embedded videos, maps and live chat.', 'tfm-tracking-consent' ),
			'cat_desc_analytics'       => __( 'Help us understand how visitors use the site so we can improve it. Data is aggregated.', 'tfm-tracking-consent' ),
			'cat_desc_advertising'     => __( 'Used by advertising partners to measure campaigns and show relevant ads. May involve sharing data with third parties.', 'tfm-tracking-consent' ),
			'cat_desc_personalization' => __( 'Allow the site to remember choices and personalize content for you.', 'tfm-tracking-consent' ),

			// Appearance.
			'layout'              => 'card',   // bar | card | modal.
			'position'            => 'bottom-left', // bottom | top | bottom-left | bottom-right | center.
			'color_bg'            => '#ffffff',
			'color_text'          => '#1f2937',
			'color_btn_bg'        => '#1f2937',
			'color_btn_text'      => '#ffffff',
			'color_link'          => '#1d4ed8',
			'border_radius'       => 12,
			'custom_css'          => '',
			'elementor_template_id' => 0,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( TFM_TC_OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Fallback when key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Flush the runtime cache (used after saving).
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Consent categories (slug => label). Necessary is implicit and always granted.
	 *
	 * @return array
	 */
	public static function categories() {
		return array(
			'necessary'       => __( 'Strictly Necessary', 'tfm-tracking-consent' ),
			'functional'      => __( 'Functional', 'tfm-tracking-consent' ),
			'analytics'       => __( 'Analytics', 'tfm-tracking-consent' ),
			'advertising'     => __( 'Advertising', 'tfm-tracking-consent' ),
			'personalization' => __( 'Personalization', 'tfm-tracking-consent' ),
		);
	}

	/**
	 * Sanitize the settings array coming from the admin form.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$out      = self::all(); // Start from current so unchecked tabs are preserved.

		$booleans = array( 'enabled', 'blocking_enabled', 'block_iframes', 'respect_gpc', 'logging_enabled', 'delete_on_uninstall', 'consent_mode', 'ads_data_redaction', 'url_passthrough' );
		foreach ( $booleans as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$out['cookie_expiry_days'] = isset( $input['cookie_expiry_days'] ) ? min( 730, max( 1, absint( $input['cookie_expiry_days'] ) ) ) : $defaults['cookie_expiry_days'];
		$out['consent_version']    = isset( $input['consent_version'] ) ? max( 1, absint( $input['consent_version'] ) ) : $defaults['consent_version'];

		if ( isset( $input['gtm_id'] ) ) {
			$gtm           = strtoupper( sanitize_text_field( $input['gtm_id'] ) );
			$out['gtm_id'] = preg_match( '/^GTM-[A-Z0-9]{4,10}$/', $gtm ) ? $gtm : '';
		}
		$out['gtm_mode'] = ( isset( $input['gtm_mode'] ) && in_array( $input['gtm_mode'], array( 'blocked', 'consent_mode' ), true ) ) ? $input['gtm_mode'] : 'blocked';

		// ISO country codes only — this value is interpolated into the Consent
		// Mode snippet, so strip anything that is not a code or separator.
		if ( isset( $input['consent_mode_region'] ) ) {
			$region                        = strtoupper( sanitize_text_field( $input['consent_mode_region'] ) );
			$out['consent_mode_region']    = preg_replace( '/[^A-Z0-9,\- ]/', '', $region );
		}

		$out['disabled_services'] = array();
		if ( ! empty( $input['disabled_services'] ) && is_array( $input['disabled_services'] ) ) {
			$valid = array_keys( TFM_TC_Services::all() );
			foreach ( $input['disabled_services'] as $slug ) {
				$slug = sanitize_key( $slug );
				if ( in_array( $slug, $valid, true ) ) {
					$out['disabled_services'][] = $slug;
				}
			}
		}

		if ( isset( $input['custom_patterns'] ) ) {
			$out['custom_patterns'] = self::sanitize_custom_patterns( $input['custom_patterns'] );
		}

		$texts = array( 'heading', 'btn_accept', 'btn_reject', 'btn_prefs', 'btn_save' );
		foreach ( $texts as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$value       = sanitize_text_field( $input[ $key ] );
				$out[ $key ] = ( '' !== $value ) ? $value : $defaults[ $key ];
			}
		}

		if ( isset( $input['message'] ) ) {
			$out['message'] = wp_kses_post( $input['message'] );
		}
		foreach ( array_keys( self::categories() ) as $cat ) {
			$key = 'cat_desc_' . $cat;
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_textarea_field( $input[ $key ] );
			}
		}

		if ( isset( $input['privacy_url'] ) ) {
			$out['privacy_url'] = esc_url_raw( $input['privacy_url'] );
		}

		$out['layout']   = ( isset( $input['layout'] ) && in_array( $input['layout'], array( 'bar', 'card', 'modal' ), true ) ) ? $input['layout'] : $defaults['layout'];
		$out['position'] = ( isset( $input['position'] ) && in_array( $input['position'], array( 'bottom', 'top', 'bottom-left', 'bottom-right', 'center' ), true ) ) ? $input['position'] : $defaults['position'];

		foreach ( array( 'color_bg', 'color_text', 'color_btn_bg', 'color_btn_text', 'color_link' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$color       = sanitize_hex_color( $input[ $key ] );
				$out[ $key ] = $color ? $color : $defaults[ $key ];
			}
		}

		$out['border_radius'] = isset( $input['border_radius'] ) ? min( 50, absint( $input['border_radius'] ) ) : $defaults['border_radius'];

		if ( isset( $input['custom_css'] ) ) {
			// Strip anything that could break out of a <style> context.
			$css               = (string) $input['custom_css'];
			$css               = wp_strip_all_tags( $css );
			$out['custom_css'] = str_replace( array( '<', '>' ), '', $css );
		}

		$out['elementor_template_id'] = isset( $input['elementor_template_id'] ) ? absint( $input['elementor_template_id'] ) : 0;

		self::flush_cache();

		return $out;
	}

	/**
	 * Sanitize the custom pattern textarea. Format: one "pattern|category" per line.
	 *
	 * @param string $raw Raw textarea value.
	 * @return string
	 */
	private static function sanitize_custom_patterns( $raw ) {
		$lines = preg_split( '/[\r\n]+/', (string) $raw );
		$valid = array_keys( self::categories() );
		$clean = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts    = array_map( 'trim', explode( '|', $line ) );
			$pattern  = sanitize_text_field( $parts[0] );
			$category = isset( $parts[1] ) ? sanitize_key( $parts[1] ) : 'advertising';
			// Optional third field: a display label shown in [tfm_cookie_declaration].
			$label    = isset( $parts[2] ) ? sanitize_text_field( $parts[2] ) : '';
			if ( '' === $pattern || strlen( $pattern ) < 4 ) {
				continue;
			}
			if ( ! in_array( $category, $valid, true ) || 'necessary' === $category ) {
				$category = 'advertising';
			}
			$clean[] = '' !== $label
				? $pattern . '|' . $category . '|' . $label
				: $pattern . '|' . $category;
		}

		return implode( "\n", array_unique( $clean ) );
	}

	/**
	 * Parse custom patterns into pattern => category pairs.
	 *
	 * @return array
	 */
	public static function custom_patterns() {
		$out = array();
		foreach ( preg_split( '/[\r\n]+/', (string) self::get( 'custom_patterns' ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '|' ) ) {
				continue;
			}
			// pattern|category[|label] — the blocking map only needs pattern + category.
			$parts    = array_map( 'trim', explode( '|', $line ) );
			$pattern  = $parts[0];
			$category = isset( $parts[1] ) ? $parts[1] : '';
			if ( '' !== $pattern && '' !== $category ) {
				$out[ $pattern ] = $category;
			}
		}
		return $out;
	}

	/**
	 * Custom patterns that carry a display label, as { label, category } pairs, so
	 * custom-blocked vendors can appear in the [tfm_cookie_declaration] table.
	 * Only lines with a third "|Label" field are returned.
	 *
	 * @return array
	 */
	public static function custom_pattern_vendors() {
		$out = array();
		foreach ( preg_split( '/[\r\n]+/', (string) self::get( 'custom_patterns' ) ) as $line ) {
			$line  = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts    = array_map( 'trim', explode( '|', $line ) );
			$category = isset( $parts[1] ) ? $parts[1] : '';
			$label    = isset( $parts[2] ) ? $parts[2] : '';
			if ( '' !== $label && '' !== $category ) {
				$out[] = array( 'label' => $label, 'category' => $category );
			}
		}
		return $out;
	}
}
