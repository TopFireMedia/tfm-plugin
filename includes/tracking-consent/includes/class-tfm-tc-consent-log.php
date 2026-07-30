<?php
/**
 * Optional consent receipt logging (proof of consent).
 *
 * Stores only what is needed to evidence a consent decision: timestamp,
 * consent version, action, category map, a salted one-way hash of the IP
 * (never the raw IP) and a truncated user agent.
 *
 * @package TFM_Tracking_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Consent log handler.
 */
class TFM_TC_Consent_Log {

	const TABLE = 'tfm_tc_consent_log';

	/**
	 * Hook in.
	 */
	public function __construct() {
		if ( ! TFM_TC_Settings::get( 'logging_enabled' ) ) {
			return;
		}
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create the log table (idempotent).
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				created_at DATETIME NOT NULL,
				consent_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
				action VARCHAR(20) NOT NULL DEFAULT '',
				categories VARCHAR(500) NOT NULL DEFAULT '',
				ip_hash CHAR(32) NOT NULL DEFAULT '',
				user_agent VARCHAR(200) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY created_at (created_at)
			) {$charset};"
		);
	}

	/**
	 * REST route for recording consent decisions from the frontend.
	 * Public by design: consent is given by anonymous visitors, so the
	 * endpoint validates and rate-limits instead of requiring auth.
	 */
	public function register_route() {
		register_rest_route(
			'tfm-tc/v1',
			'/log',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'handle_log' ),
				'args'                => array(
					'action'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'categories' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Salted, truncated hash used for rate limiting and pseudonymous
	 * consent receipts. Raw IPs are never stored.
	 *
	 * @return string
	 */
	private function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Handle a consent receipt.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_log( WP_REST_Request $request ) {
		if ( ! TFM_TC_Settings::get( 'logging_enabled' ) ) {
			return new WP_Error( 'tfm_tc_disabled', __( 'Consent logging is disabled.', 'tfm-tracking-consent' ), array( 'status' => 404 ) );
		}

		$ip_hash = $this->ip_hash();

		// Rate limit: max 10 receipts per IP hash per hour.
		$rl_key = 'tfm_tc_rl_' . $ip_hash;
		$count  = (int) get_transient( $rl_key );
		if ( $count >= 10 ) {
			return new WP_Error( 'tfm_tc_rate_limited', __( 'Too many requests.', 'tfm-tracking-consent' ), array( 'status' => 429 ) );
		}
		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

		$action = $request->get_param( 'action' );
		if ( ! in_array( $action, array( 'accept_all', 'reject_all', 'save_preferences', 'gpc', 'do_not_sell' ), true ) ) {
			return new WP_Error( 'tfm_tc_bad_action', __( 'Invalid action.', 'tfm-tracking-consent' ), array( 'status' => 400 ) );
		}

		$raw_categories = (array) $request->get_param( 'categories' );
		$clean          = array();
		foreach ( array_keys( TFM_TC_Settings::categories() ) as $slug ) {
			$clean[ $slug ] = ! empty( $raw_categories[ $slug ] );
		}
		$clean['necessary'] = true;

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 200 ) : '';

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'created_at'      => current_time( 'mysql', true ),
				'consent_version' => (int) TFM_TC_Settings::get( 'consent_version' ),
				'action'          => $action,
				'categories'      => wp_json_encode( $clean ),
				'ip_hash'         => $ip_hash,
				'user_agent'      => $user_agent,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return new WP_REST_Response( array( 'ok' => true ), 201 );
	}

	/**
	 * Query log entries for the admin screen.
	 *
	 * @param int $page     Page number (1-based).
	 * @param int $per_page Rows per page.
	 * @return array{rows: array, total: int}
	 */
	public static function query( $page = 1, $per_page = 50 ) {
		global $wpdb;

		$table = self::table();
		// Table name comes from $wpdb->prefix + constant; safe to interpolate.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				max( 0, ( $page - 1 ) * $per_page )
			),
			ARRAY_A
		);

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}
}
