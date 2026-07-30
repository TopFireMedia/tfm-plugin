<?php
/**
 * Shortcodes for consent controls — usable in content, widgets and
 * Elementor Pro templates (shortcode / text widgets).
 *
 * @package TFM_Tracking_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode registrar.
 */
class TFM_TC_Shortcodes {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_shortcode( 'tfm_consent_button', array( $this, 'button' ) );
		add_shortcode( 'tfm_consent_link', array( $this, 'link' ) );
		add_shortcode( 'tfm_do_not_sell', array( $this, 'do_not_sell' ) );
		add_shortcode( 'tfm_cookie_declaration', array( $this, 'declaration' ) );
	}

	/**
	 * [tfm_consent_button action="accept|reject|preferences" label="" class=""]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function button( $atts ) {
		$atts = shortcode_atts(
			array(
				'action' => 'preferences',
				'label'  => '',
				'class'  => '',
			),
			$atts,
			'tfm_consent_button'
		);

		$action = in_array( $atts['action'], array( 'accept', 'reject', 'preferences' ), true ) ? $atts['action'] : 'preferences';
		if ( '' === $atts['label'] ) {
			$labels = array(
				'accept'      => TFM_TC_Settings::get( 'btn_accept' ),
				'reject'      => TFM_TC_Settings::get( 'btn_reject' ),
				'preferences' => TFM_TC_Settings::get( 'btn_prefs' ),
			);
			$atts['label'] = $labels[ $action ];
		}

		return sprintf(
			'<button type="button" class="tfm-tc-btn %1$s" data-tfm-tc-action="%2$s">%3$s</button>',
			esc_attr( $atts['class'] ),
			esc_attr( $action ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * [tfm_consent_link label=""] — plain link that opens the preferences
	 * panel. Intended for footers and privacy pages.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function link( $atts ) {
		$atts = shortcode_atts(
			array(
				'label' => __( 'Manage Cookie Preferences', 'tfm-tracking-consent' ),
				'class' => '',
			),
			$atts,
			'tfm_consent_link'
		);

		return sprintf(
			'<a href="#" class="tfm-tc-link %1$s" data-tfm-tc-action="preferences" role="button">%2$s</a>',
			esc_attr( $atts['class'] ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * [tfm_do_not_sell] — CCPA/CPRA "Do Not Sell or Share My Personal
	 * Information" link.
	 *
	 * Acts as a one-click opt-out rather than just opening the preferences
	 * panel: it withdraws the Advertising and Personalization categories, which
	 * are the ones constituting a "sale" or "share", and leaves the visitor's
	 * other choices alone (analytics and functional are business purposes, not a
	 * sale). Requiring someone to open a panel, locate a toggle and save is more
	 * steps than an opt-out link should take.
	 *
	 * Pass mode="preferences" to get the old behaviour of opening the panel.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function do_not_sell( $atts ) {
		$atts = shortcode_atts(
			array(
				'label' => __( 'Do Not Sell or Share My Personal Information', 'tfm-tracking-consent' ),
				'class' => '',
				'mode'  => 'optout',
			),
			$atts,
			'tfm_do_not_sell'
		);

		$action = ( 'preferences' === $atts['mode'] ) ? 'preferences' : 'optout';

		return sprintf(
			'<a href="#" class="tfm-tc-link tfm-tc-dns %1$s" data-tfm-tc-action="%2$s" role="button">%3$s</a>',
			esc_attr( $atts['class'] ),
			esc_attr( $action ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * [tfm_cookie_declaration] — renders the category table for privacy
	 * policy pages, including the services blocked in each category.
	 *
	 * Attributes:
	 *   heading — optional visible heading above the table. Empty by default so
	 *             existing pages are unchanged.
	 *   level   — heading tag to use, h2-h4. Defaults to h2.
	 *   intro   — optional sentence between the heading and the table.
	 *
	 * A caption is always emitted so the table has an accessible name even when
	 * no visible heading is requested; it is visually hidden if a heading is
	 * shown, to avoid saying the same thing twice.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function declaration( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'heading' => '',
				'level'   => 'h2',
				'intro'   => '',
			),
			$atts,
			'tfm_cookie_declaration'
		);

		$level = in_array( strtolower( $atts['level'] ), array( 'h2', 'h3', 'h4' ), true )
			? strtolower( $atts['level'] )
			: 'h2';

		$categories = TFM_TC_Settings::categories();
		$disabled   = (array) TFM_TC_Settings::get( 'disabled_services', array() );
		$services   = TFM_TC_Services::all();

		$by_category = array();
		foreach ( $services as $slug => $service ) {
			if ( in_array( $slug, $disabled, true ) ) {
				continue;
			}
			$by_category[ $service['category'] ][] = $service['label'];
		}

		$caption = __( 'Cookie and tracking technology categories used on this website', 'tfm-tracking-consent' );

		ob_start();

		if ( '' !== $atts['heading'] ) {
			printf( '<%1$s class="tfm-tc-declaration-heading">%2$s</%1$s>', esc_html( $level ), esc_html( $atts['heading'] ) );
		}
		if ( '' !== $atts['intro'] ) {
			echo '<p class="tfm-tc-declaration-intro">' . esc_html( $atts['intro'] ) . '</p>';
		}

		echo '<table class="tfm-tc-declaration">';
		printf(
			'<caption class="%1$s">%2$s</caption>',
			'' !== $atts['heading'] ? 'tfm-tc-visually-hidden' : 'tfm-tc-declaration-caption',
			esc_html( $caption )
		);
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Category', 'tfm-tracking-consent' ) . '</th>';
		echo '<th>' . esc_html__( 'Purpose', 'tfm-tracking-consent' ) . '</th>';
		echo '<th>' . esc_html__( 'Services', 'tfm-tracking-consent' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $categories as $slug => $label ) {
			$desc     = (string) TFM_TC_Settings::get( 'cat_desc_' . $slug );
			$vendors  = isset( $by_category[ $slug ] ) ? implode( ', ', $by_category[ $slug ] ) : '—';
			echo '<tr>';
			echo '<td>' . esc_html( $label ) . '</td>';
			echo '<td>' . esc_html( $desc ) . '</td>';
			echo '<td>' . esc_html( 'necessary' === $slug ? __( 'WordPress core, consent storage', 'tfm-tracking-consent' ) : $vendors ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		return ob_get_clean();
	}
}
