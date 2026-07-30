<?php
/**
 * Plugin orchestrator.
 *
 * @package TFM_Tracking_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Boots all components.
 */
final class TFM_TC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var TFM_TC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get / create the instance.
	 *
	 * @return TFM_TC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot components.
	 */
	private function __construct() {
		new TFM_TC_Blocker();
		new TFM_TC_Frontend();
		new TFM_TC_Shortcodes();
		new TFM_TC_Consent_Log();

		if ( is_admin() && class_exists( 'TFM_TC_Admin' ) ) {
			new TFM_TC_Admin();
		}
	}

	/**
	 * Activation: seed defaults and create the consent log table.
	 */
	public static function activate() {
		if ( false === get_option( TFM_TC_OPTION, false ) ) {
			add_option( TFM_TC_OPTION, TFM_TC_Settings::defaults() );
		}
		TFM_TC_Consent_Log::create_table();
	}
}
