<?php
/**
 * Prior-consent script blocker.
 *
 * Rewrites known tracking <script> and <iframe> tags in the final HTML to
 * inert placeholders (type="text/plain" / data-src). The frontend script
 * re-activates them only after the visitor grants the matching category.
 * Because blocking always happens server-side and unblocking happens
 * client-side, the output is identical for every visitor and fully
 * compatible with page caching.
 *
 * @package TFM_Tracking_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output-buffer based auto blocker.
 */
class TFM_TC_Blocker {

	/**
	 * Pattern => category map.
	 *
	 * @var array
	 */
	private $patterns = array();

	/**
	 * Whether the buffer was started.
	 *
	 * @var bool
	 */
	private $started = false;

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'start_buffer' ), 0 );
	}

	/**
	 * Whether blocking should run for this request.
	 *
	 * @return bool
	 */
	private function should_run() {
		if ( ! TFM_TC_Settings::get( 'enabled' ) || ! TFM_TC_Settings::get( 'blocking_enabled' ) ) {
			return false;
		}
		if ( is_admin() || is_feed() || is_embed() || is_customize_preview() ) {
			return false;
		}
		if ( wp_doing_ajax() || wp_doing_cron() || wp_is_json_request() || wp_is_xml_request() ) {
			return false;
		}
		// Never rewrite inside page-builder editors/previews.
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['et_fb'] ) || isset( $_GET['fl_builder'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		/**
		 * Filter whether the auto-blocker runs for the current request.
		 *
		 * @param bool $run Whether to run.
		 */
		return (bool) apply_filters( 'tfm_tc_blocker_should_run', true );
	}

	/**
	 * Start output buffering on frontend requests.
	 */
	public function start_buffer() {
		if ( ! $this->should_run() ) {
			return;
		}

		$this->patterns = TFM_TC_Services::active_patterns();
		if ( empty( $this->patterns ) ) {
			return;
		}

		$this->started = true;
		ob_start( array( $this, 'process_output' ) );
	}

	/**
	 * Buffer callback: rewrite matched scripts and iframes.
	 *
	 * @param string $html Buffered output.
	 * @return string
	 */
	public function process_output( $html ) {
		// Only touch full HTML documents.
		if ( '' === $html || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		$html = preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			array( $this, 'rewrite_script' ),
			$html
		);

		if ( TFM_TC_Settings::get( 'block_iframes' ) ) {
			$html = preg_replace_callback(
				'#<iframe\b[^>]*>#i',
				array( $this, 'rewrite_iframe' ),
				$html
			);
		}

		return $html;
	}

	/**
	 * Find the consent category for a string, if any pattern matches.
	 *
	 * @param string $subject Script src or inline code.
	 * @return string Empty string when no match.
	 */
	private function match_category( $subject ) {
		if ( '' === $subject ) {
			return '';
		}
		foreach ( $this->patterns as $pattern => $category ) {
			if ( false !== stripos( $subject, $pattern ) ) {
				return $category;
			}
		}
		return '';
	}

	/**
	 * Rewrite a matched <script> tag to an inert placeholder.
	 *
	 * @param array $m Regex match: full tag, attributes, inline body.
	 * @return string
	 */
	private function rewrite_script( $m ) {
		$tag   = $m[0];
		$attrs = $m[1];
		$body  = $m[2];

		// Skip our own scripts (including the inline config, whose pattern
		// list would otherwise match), already-processed tags and opt-outs.
		if ( false !== stripos( $attrs, 'data-tfm-tc' ) || false !== stripos( $attrs, 'tfm-tc-js' ) || false !== stripos( $body, 'tfmTcConfig' ) ) {
			return $tag;
		}
		// Skip non-JS script tags (JSON-LD, templates) — they execute nothing.
		if ( preg_match( '#type\s*=\s*["\']?(application/(ld\+)?json|text/template|text/x-template)#i', $attrs ) ) {
			return $tag;
		}

		$src      = '';
		$category = '';
		if ( preg_match( '#\bsrc\s*=\s*("|\')(.*?)\1#i', $attrs, $sm ) ) {
			$src      = $sm[2];
			$category = $this->match_category( $src );
		}
		if ( '' === $category && '' !== trim( $body ) ) {
			$category = $this->match_category( $body );
		}
		if ( '' === $category ) {
			return $tag;
		}

		// Neutralize: replace type with text/plain, rename src, tag category.
		$attrs = preg_replace( '#\stype\s*=\s*("|\')[^"\']*\1#i', '', $attrs );
		$attrs = preg_replace( '#\bsrc\s*=#i', 'data-tfm-tc-src=', $attrs );

		return '<script type="text/plain" data-tfm-tc-category="' . esc_attr( $category ) . '"' . $attrs . '>' . $body . '</script>';
	}

	/**
	 * Rewrite a matched <iframe> opening tag to an inert placeholder.
	 *
	 * @param array $m Regex match.
	 * @return string
	 */
	private function rewrite_iframe( $m ) {
		$tag = $m[0];

		if ( false !== stripos( $tag, 'data-tfm-tc' ) ) {
			return $tag;
		}
		if ( ! preg_match( '#\bsrc\s*=\s*("|\')(.*?)\1#i', $tag, $sm ) ) {
			return $tag;
		}

		$category = $this->match_category( $sm[2] );
		if ( '' === $category ) {
			return $tag;
		}

		$tag = preg_replace( '#\bsrc\s*=#i', 'data-tfm-tc-src=', $tag );
		return preg_replace( '#^<iframe\b#i', '<iframe data-tfm-tc-category="' . esc_attr( $category ) . '"', $tag );
	}
}
