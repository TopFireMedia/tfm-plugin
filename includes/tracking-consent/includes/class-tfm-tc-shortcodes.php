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
	 * Information" link. Opens preferences with advertising highlighted.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function do_not_sell( $atts ) {
		$atts = shortcode_atts(
			array(
				'label' => __( 'Do Not Sell or Share My Personal Information', 'tfm-tracking-consent' ),
				'class' => '',
			),
			$atts,
			'tfm_do_not_sell'
		);

		return sprintf(
			'<a href="#" class="tfm-tc-link tfm-tc-dns %1$s" data-tfm-tc-action="preferences" role="button">%2$s</a>',
			esc_attr( $atts['class'] ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * [tfm_cookie_declaration] — renders the category table for privacy
	 * policy pages, including the services blocked in each category.
	 *
	 * @return string
	 */
	public function declaration() {
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

		ob_start();
		echo '<table class="tfm-tc-declaration"><thead><tr>';
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
