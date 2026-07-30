<?php
/**
 * Frontend output: Consent Mode v2 defaults, banner markup and assets.
 *
 * @package TFM_Tracking_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend handler.
 */
class TFM_TC_Frontend {

	/**
	 * Hook in.
	 */
	public function __construct() {
		if ( ! TFM_TC_Settings::get( 'enabled' ) ) {
			return;
		}

		// Consent Mode v2 defaults must print before any Google snippet.
		add_action( 'wp_head', array( $this, 'print_consent_mode_defaults' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_banner' ), 5 );
	}

	/**
	 * Inline Consent Mode v2 default snippet. Reads the consent cookie so
	 * returning visitors who already granted consent get correct defaults
	 * without a denied-then-updated flicker.
	 */
	public function print_consent_mode_defaults() {
		if ( ! TFM_TC_Settings::get( 'consent_mode' ) ) {
			return;
		}

		$redact  = TFM_TC_Settings::get( 'ads_data_redaction' ) ? 'true' : 'false';
		$version = (int) TFM_TC_Settings::get( 'consent_version' );

		// Preserves ad click IDs in the URL while storage is denied, so
		// conversion measurement degrades instead of disappearing. Off by
		// default because it rewrites outbound links.
		$passthrough = TFM_TC_Settings::get( 'url_passthrough' ) ? 'true' : 'false';

		// Optional ISO country codes limiting the denied default to those
		// regions. Empty means apply denied-by-default worldwide, which is the
		// safer choice under US state privacy laws.
		$region_js = '';
		$region    = trim( (string) TFM_TC_Settings::get( 'consent_mode_region' ) );
		if ( '' !== $region ) {
			// Uppercased here too: the setting sanitizer does it, but a value
			// stored before that existed, or injected via a filter, may not be.
			$codes = array_values( array_filter( array_map(
				function ( $c ) {
					return strtoupper( trim( $c ) );
				},
				explode( ',', $region )
			) ) );
			if ( ! empty( $codes ) ) {
				$region_js = "\td.region = " . wp_json_encode( $codes ) . ";\n";
			}
		}
		?>
<script data-tfm-tc-ignore="1">
(function(){
	window.dataLayer = window.dataLayer || [];
	window.gtag = window.gtag || function(){ dataLayer.push(arguments); };
	var d = { ad_storage:'denied', ad_user_data:'denied', ad_personalization:'denied', analytics_storage:'denied', functionality_storage:'denied', personalization_storage:'denied', security_storage:'granted', wait_for_update:500 };
<?php echo $region_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from sanitized ISO codes via wp_json_encode. ?>
	try {
		var m = document.cookie.match(/(?:^|;\s*)tfm_tc_consent=([^;]+)/);
		if (m) {
			var c = JSON.parse(decodeURIComponent(m[1]));
			if (c && c.v === <?php echo (int) $version; ?> && c.cats) {
				if (c.cats.analytics)       { d.analytics_storage = 'granted'; }
				if (c.cats.functional)      { d.functionality_storage = 'granted'; }
				if (c.cats.personalization) { d.personalization_storage = 'granted'; }
				if (c.cats.advertising)     { d.ad_storage = 'granted'; d.ad_user_data = 'granted'; d.ad_personalization = 'granted'; }
			}
		}
	} catch (e) {}
	gtag('consent', 'default', d);
	gtag('set', 'ads_data_redaction', <?php echo esc_js( $redact ); // phpcs:ignore ?>);
	gtag('set', 'url_passthrough', <?php echo esc_js( $passthrough ); // phpcs:ignore ?>);
})();
</script>
		<?php
	}

	/**
	 * Enqueue the (dependency-free) banner script and stylesheet.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'tfm-tc', TFM_TC_URL . 'assets/css/tfm-consent.css', array(), TFM_TC_VERSION );

		$inline = $this->build_inline_css();
		if ( $inline ) {
			wp_add_inline_style( 'tfm-tc', $inline );
		}

		wp_enqueue_script( 'tfm-tc', TFM_TC_URL . 'assets/js/tfm-consent.js', array(), TFM_TC_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );

		wp_add_inline_script( 'tfm-tc', 'window.tfmTcConfig = ' . wp_json_encode( $this->js_config() ) . ';', 'before' );
	}

	/**
	 * Configuration passed to the frontend script.
	 *
	 * @return array
	 */
	private function js_config() {
		$config = array(
			// Pattern map lets the frontend also neutralize trackers that are
			// injected dynamically (e.g. Elementor video widgets), which the
			// server-side blocker can never see.
			'patterns'    => (object) TFM_TC_Services::active_patterns(),
			'dynamic'     => (bool) TFM_TC_Settings::get( 'blocking_enabled' ),
			'version'     => (int) TFM_TC_Settings::get( 'consent_version' ),
			'expiryDays'  => (int) TFM_TC_Settings::get( 'cookie_expiry_days' ),
			'consentMode' => (bool) TFM_TC_Settings::get( 'consent_mode' ),
			'respectGpc'  => (bool) TFM_TC_Settings::get( 'respect_gpc' ),
			'gtmId'       => (string) TFM_TC_Settings::get( 'gtm_id' ),
			'gtmMode'     => (string) TFM_TC_Settings::get( 'gtm_mode' ),
			'logging'     => (bool) TFM_TC_Settings::get( 'logging_enabled' ),
			'restUrl'     => esc_url_raw( rest_url( 'tfm-tc/v1/log' ) ),
			'iframeText'  => __( 'This embedded content is blocked until you allow the related cookies.', 'tfm-tracking-consent' ),
			'iframeBtn'   => __( 'Allow and load', 'tfm-tracking-consent' ),
		);

		/**
		 * Filter the frontend JS configuration.
		 *
		 * @param array $config Config array.
		 */
		return apply_filters( 'tfm_tc_js_config', $config );
	}

	/**
	 * Build inline CSS: custom properties from appearance settings plus
	 * sanitized custom CSS.
	 *
	 * @return string
	 */
	private function build_inline_css() {
		$s   = TFM_TC_Settings::all();
		$css = sprintf(
			'#tfm-tc-root{--tfm-tc-bg:%1$s;--tfm-tc-text:%2$s;--tfm-tc-btn-bg:%3$s;--tfm-tc-btn-text:%4$s;--tfm-tc-link:%5$s;--tfm-tc-radius:%6$dpx;}',
			sanitize_hex_color( $s['color_bg'] ) ? $s['color_bg'] : '#ffffff',
			sanitize_hex_color( $s['color_text'] ) ? $s['color_text'] : '#1f2937',
			sanitize_hex_color( $s['color_btn_bg'] ) ? $s['color_btn_bg'] : '#1f2937',
			sanitize_hex_color( $s['color_btn_text'] ) ? $s['color_btn_text'] : '#ffffff',
			sanitize_hex_color( $s['color_link'] ) ? $s['color_link'] : '#1d4ed8',
			(int) $s['border_radius']
		);

		if ( ! empty( $s['custom_css'] ) ) {
			$css .= "\n" . $s['custom_css'];
		}

		return $css;
	}

	/**
	 * Render the banner + preferences modal markup in the footer.
	 * Hidden by default; the script decides whether to show it.
	 */
	public function render_banner() {
		if ( is_customize_preview() ) {
			return;
		}
		// Skip inside the Elementor editor.
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$settings   = TFM_TC_Settings::all();
		$categories = TFM_TC_Settings::categories();

		// Elementor Pro template override for the banner body.
		$elementor_content = '';
		$template_id       = (int) $settings['elementor_template_id'];
		if ( $template_id && did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) && 'publish' === get_post_status( $template_id ) ) {
			$elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id );
		}

		$template = locate_template( 'tfm-tracking-consent/banner.php' );
		if ( ! $template ) {
			$template = TFM_TC_DIR . 'templates/banner.php';
		}

		/**
		 * Filter the banner template path. Themes can also override via
		 * a `tfm-tracking-consent/banner.php` theme file.
		 *
		 * @param string $template Absolute path.
		 */
		$template = apply_filters( 'tfm_tc_banner_template', $template );

		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}
