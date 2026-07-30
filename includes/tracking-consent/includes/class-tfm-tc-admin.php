<?php
/**
 * Admin UI: settings screen and consent log viewer.
 *
 * @package TFM_Tracking_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin handler.
 */
class TFM_TC_Admin {

	const CAP        = 'manage_options';
	const PAGE_SLUG  = 'tfm-tracking-consent';
	const LOG_SLUG   = 'tfm-tc-consent-log';

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_tfm_tc_export_log', array( $this, 'export_log_csv' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TFM_TC_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'tfm-tracking-consent' ) . '</a>' );
		return $links;
	}

	/**
	 * Register menu pages.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'TFM Tracking Consent', 'tfm-tracking-consent' ),
			__( 'TFM Consent', 'tfm-tracking-consent' ),
			self::CAP,
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-privacy',
			81
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Settings', 'tfm-tracking-consent' ),
			__( 'Settings', 'tfm-tracking-consent' ),
			self::CAP,
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);

		if ( TFM_TC_Settings::get( 'logging_enabled' ) ) {
			add_submenu_page(
				self::PAGE_SLUG,
				__( 'Consent Log', 'tfm-tracking-consent' ),
				__( 'Consent Log', 'tfm-tracking-consent' ),
				self::CAP,
				self::LOG_SLUG,
				array( $this, 'render_log_page' )
			);
		}
	}

	/**
	 * Register the (single, sanitized) settings option.
	 */
	public function register_settings() {
		register_setting(
			'tfm_tc',
			TFM_TC_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'TFM_TC_Settings', 'sanitize' ),
			)
		);
	}

	/**
	 * Admin assets, only on our screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, self::PAGE_SLUG ) && false === strpos( $hook, self::LOG_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'tfm-tc-admin', TFM_TC_URL . 'assets/css/admin.css', array(), TFM_TC_VERSION );
		wp_enqueue_script( 'tfm-tc-admin', TFM_TC_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), TFM_TC_VERSION, true );
	}

	/* ---------------------------------------------------------------------
	 * Settings screen
	 * ------------------------------------------------------------------ */

	/**
	 * Render the tabbed settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tfm-tracking-consent' ) );
		}

		$s    = TFM_TC_Settings::all();
		$tabs = array(
			'general'    => __( 'General', 'tfm-tracking-consent' ),
			'banner'     => __( 'Banner & Design', 'tfm-tracking-consent' ),
			'categories' => __( 'Categories', 'tfm-tracking-consent' ),
			'google'     => __( 'Google & GTM', 'tfm-tracking-consent' ),
			'services'   => __( 'Blocked Services', 'tfm-tracking-consent' ),
			'advanced'   => __( 'Advanced', 'tfm-tracking-consent' ),
		);
		?>
		<div class="wrap tfm-tc-wrap">
			<h1><?php esc_html_e( 'TFM Tracking Consent', 'tfm-tracking-consent' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Prior-consent blocking, Google Consent Mode v2 and category-based preferences — by TopFire Media.', 'tfm-tracking-consent' ); ?></p>

			<h2 class="nav-tab-wrapper tfm-tc-tabs">
				<?php foreach ( $tabs as $id => $label ) : ?>
					<a href="#tfm-tab-<?php echo esc_attr( $id ); ?>" class="nav-tab<?php echo 'general' === $id ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'tfm_tc' ); ?>

				<div id="tfm-tab-general" class="tfm-tc-tab is-active">
					<table class="form-table" role="presentation">
						<?php
						$this->checkbox( 'enabled', __( 'Enable consent management', 'tfm-tracking-consent' ), __( 'Master switch. Disabling stops the banner, blocking and Consent Mode output.', 'tfm-tracking-consent' ), $s );
						$this->checkbox( 'blocking_enabled', __( 'Automatic prior-consent blocking', 'tfm-tracking-consent' ), __( 'Rewrites known tracking scripts to stay inert until the visitor consents. This is the core compliance feature — leave enabled.', 'tfm-tracking-consent' ), $s );
						$this->checkbox( 'block_iframes', __( 'Block tracking iframes', 'tfm-tracking-consent' ), __( 'Also hold back embedded iframes (YouTube, Maps, ad frames) until consent, showing a placeholder.', 'tfm-tracking-consent' ), $s );
						$this->checkbox( 'respect_gpc', __( 'Honor Global Privacy Control (GPC)', 'tfm-tracking-consent' ), __( 'Treat the browser GPC signal as an opt-out of Advertising and Personalization, as required by the CCPA/CPRA.', 'tfm-tracking-consent' ), $s );
						$this->number( 'cookie_expiry_days', __( 'Consent cookie lifetime (days)', 'tfm-tracking-consent' ), __( 'How long a consent choice is remembered before the banner is shown again. 365 or less recommended.', 'tfm-tracking-consent' ), $s, 1, 730 );
						$this->number( 'consent_version', __( 'Consent version', 'tfm-tracking-consent' ), __( 'Increase this to re-prompt all visitors (e.g. after adding a new tracking service).', 'tfm-tracking-consent' ), $s, 1, 9999 );
						$this->checkbox( 'logging_enabled', __( 'Consent receipt logging', 'tfm-tracking-consent' ), __( 'Store pseudonymous proof-of-consent records (no raw IP addresses). Adds a Consent Log screen.', 'tfm-tracking-consent' ), $s );
						?>
					</table>
				</div>

				<div id="tfm-tab-banner" class="tfm-tc-tab">
					<table class="form-table" role="presentation">
						<?php
						$this->text( 'heading', __( 'Banner heading', 'tfm-tracking-consent' ), '', $s );
						$this->textarea( 'message', __( 'Banner message', 'tfm-tracking-consent' ), __( 'Basic HTML allowed (links, bold).', 'tfm-tracking-consent' ), $s );
						$this->text( 'privacy_url', __( 'Privacy policy URL', 'tfm-tracking-consent' ), __( 'Linked from the banner. Strongly recommended.', 'tfm-tracking-consent' ), $s, 'url' );
						$this->text( 'btn_accept', __( '“Accept” button label', 'tfm-tracking-consent' ), '', $s );
						$this->text( 'btn_reject', __( '“Reject” button label', 'tfm-tracking-consent' ), '', $s );
						$this->text( 'btn_prefs', __( '“Preferences” button label', 'tfm-tracking-consent' ), '', $s );
						$this->text( 'btn_save', __( '“Save” button label', 'tfm-tracking-consent' ), '', $s );
						$this->select( 'layout', __( 'Layout', 'tfm-tracking-consent' ), $s, array(
							'bar'   => __( 'Full-width bar', 'tfm-tracking-consent' ),
							'card'  => __( 'Floating card', 'tfm-tracking-consent' ),
							'modal' => __( 'Centered modal', 'tfm-tracking-consent' ),
						) );
						$this->select( 'position', __( 'Position', 'tfm-tracking-consent' ), $s, array(
							'bottom'       => __( 'Bottom', 'tfm-tracking-consent' ),
							'top'          => __( 'Top', 'tfm-tracking-consent' ),
							'bottom-left'  => __( 'Bottom left', 'tfm-tracking-consent' ),
							'bottom-right' => __( 'Bottom right', 'tfm-tracking-consent' ),
							'center'       => __( 'Center', 'tfm-tracking-consent' ),
						) );
						$this->color( 'color_bg', __( 'Background color', 'tfm-tracking-consent' ), $s );
						$this->color( 'color_text', __( 'Text color', 'tfm-tracking-consent' ), $s );
						$this->color( 'color_btn_bg', __( 'Button background', 'tfm-tracking-consent' ), $s );
						$this->color( 'color_btn_text', __( 'Button text', 'tfm-tracking-consent' ), $s );
						$this->color( 'color_link', __( 'Link color', 'tfm-tracking-consent' ), $s );
						$this->number( 'border_radius', __( 'Corner radius (px)', 'tfm-tracking-consent' ), '', $s, 0, 50 );
						$this->number( 'elementor_template_id', __( 'Elementor template ID', 'tfm-tracking-consent' ), __( 'Optional. ID of an Elementor Pro saved template to use as the banner body instead of the heading/message above. Add consent buttons inside it with the [tfm_consent_button] shortcode.', 'tfm-tracking-consent' ), $s, 0, PHP_INT_MAX );
						$this->textarea( 'custom_css', __( 'Custom CSS', 'tfm-tracking-consent' ), __( 'Scoped additions, e.g. #tfm-tc-root .tfm-tc-banner { font-size: 15px; }', 'tfm-tracking-consent' ), $s, 8 );
						?>
					</table>
				</div>

				<div id="tfm-tab-categories" class="tfm-tc-tab">
					<p class="description"><?php esc_html_e( 'Descriptions shown in the preferences panel. Visitors can toggle each category independently; Strictly Necessary is always on.', 'tfm-tracking-consent' ); ?></p>
					<table class="form-table" role="presentation">
						<?php
						foreach ( TFM_TC_Settings::categories() as $slug => $label ) {
							$this->textarea( 'cat_desc_' . $slug, $label, '', $s, 3 );
						}
						?>
					</table>
				</div>

				<div id="tfm-tab-google" class="tfm-tc-tab">
					<table class="form-table" role="presentation">
						<?php
						$this->checkbox( 'consent_mode', __( 'Google Consent Mode v2', 'tfm-tracking-consent' ), __( 'Print consent defaults (all non-essential storage denied) before any Google tag, and send updates when the visitor decides. Required when using GA4, Google Ads or GTM.', 'tfm-tracking-consent' ), $s );
						$this->checkbox( 'ads_data_redaction', __( 'Redact ads data when denied', 'tfm-tracking-consent' ), __( 'Sets ads_data_redaction so ad click identifiers are dropped while ad_storage is denied.', 'tfm-tracking-consent' ), $s );
						$this->checkbox( 'url_passthrough', __( 'URL passthrough', 'tfm-tracking-consent' ), __( 'Keeps ad click IDs in the URL between pages while storage is denied, so conversion measurement degrades instead of disappearing. Useful for clients running Google Ads; leave off if you would rather not have outbound links rewritten.', 'tfm-tracking-consent' ), $s );
						$this->text( 'consent_mode_region', __( 'Restrict denied default to regions', 'tfm-tracking-consent' ), __( 'Optional comma-separated ISO country codes, e.g. GB,DE,FR. Leave empty to deny by default worldwide — the safer choice, since US state privacy laws expect it too.', 'tfm-tracking-consent' ), $s );
						$this->text( 'gtm_id', __( 'Google Tag Manager container ID', 'tfm-tracking-consent' ), __( 'Optional, e.g. GTM-XXXXXXX. Leave empty if GTM is added by another plugin or the theme.', 'tfm-tracking-consent' ), $s );
						$this->select( 'gtm_mode', __( 'GTM loading strategy', 'tfm-tracking-consent' ), $s, array(
							'blocked'      => __( 'Strict: load GTM only after consent (recommended for this letter)', 'tfm-tracking-consent' ),
							'consent_mode' => __( 'Consent Mode: load GTM immediately with all storage denied', 'tfm-tracking-consent' ),
						) );
						?>
					</table>
					<p class="description"><?php esc_html_e( 'Strict mode guarantees no requests to googletagmanager.com before consent, which is what the complaint letter alleges. Consent Mode still loads the container (cookieless) — only choose it if the client needs modeled conversions.', 'tfm-tracking-consent' ); ?></p>
				</div>

				<div id="tfm-tab-services" class="tfm-tc-tab">
					<p class="description"><?php esc_html_e( 'Services detected and blocked automatically. Untick a service to exclude it from blocking (e.g. if it is strictly necessary for your site).', 'tfm-tracking-consent' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Built-in services', 'tfm-tracking-consent' ); ?></th>
							<td>
								<?php
								$disabled   = (array) $s['disabled_services'];
								$categories = TFM_TC_Settings::categories();
								foreach ( TFM_TC_Services::all() as $slug => $service ) :
									$cat_label = isset( $categories[ $service['category'] ] ) ? $categories[ $service['category'] ] : $service['category'];
									?>
									<label class="tfm-tc-service">
										<input type="checkbox" name="<?php echo esc_attr( TFM_TC_OPTION ); ?>[disabled_services][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $disabled, true ) ); ?> />
										<?php
										/* translators: 1: service name, 2: category */
										echo esc_html( sprintf( __( 'Exclude %1$s (%2$s)', 'tfm-tracking-consent' ), $service['label'], $cat_label ) );
										?>
									</label><br/>
								<?php endforeach; ?>
							</td>
						</tr>
						<?php
						$this->textarea( 'custom_patterns', __( 'Custom block patterns', 'tfm-tracking-consent' ), __( 'One per line: pattern|category. The pattern is matched against script/iframe URLs and inline code. Categories: functional, analytics, advertising, personalization. Example: widget.example.com|advertising', 'tfm-tracking-consent' ), $s, 6 );
						?>
					</table>
				</div>

				<div id="tfm-tab-advanced" class="tfm-tc-tab">
					<table class="form-table" role="presentation">
						<?php
						$this->checkbox( 'delete_on_uninstall', __( 'Delete all data on uninstall', 'tfm-tracking-consent' ), __( 'Removes settings and the consent log table when the plugin is deleted.', 'tfm-tracking-consent' ), $s );
						?>
					</table>
					<h3><?php esc_html_e( 'Shortcodes', 'tfm-tracking-consent' ); ?></h3>
					<p><code>[tfm_consent_button action="accept|reject|preferences"]</code> — <?php esc_html_e( 'consent button (also for Elementor templates)', 'tfm-tracking-consent' ); ?><br/>
					<code>[tfm_consent_link]</code> — <?php esc_html_e( 'footer link that reopens the preferences panel', 'tfm-tracking-consent' ); ?><br/>
					<code>[tfm_do_not_sell]</code> — <?php esc_html_e( '“Do Not Sell or Share My Personal Information” link (CCPA/CPRA)', 'tfm-tracking-consent' ); ?><br/>
					<code>[tfm_cookie_declaration]</code> — <?php esc_html_e( 'cookie category table for the privacy policy page', 'tfm-tracking-consent' ); ?></p>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* Field helpers -------------------------------------------------------- */

	/**
	 * Field name attribute.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function name( $key ) {
		return TFM_TC_OPTION . '[' . $key . ']';
	}

	/**
	 * Checkbox row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Description.
	 * @param array  $s     Settings.
	 */
	private function checkbox( $key, $label, $help, $s ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?> />
					<?php echo esc_html( $help ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Text row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Description.
	 * @param array  $s     Settings.
	 * @param string $type  Input type.
	 */
	private function text( $key, $label, $help, $s, $type = 'text' ) {
		?>
		<tr>
			<th scope="row"><label for="tfm-tc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>" class="regular-text" id="tfm-tc-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" />
				<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Number row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Description.
	 * @param array  $s     Settings.
	 * @param int    $min   Min.
	 * @param int    $max   Max.
	 */
	private function number( $key, $label, $help, $s, $min = 0, $max = 9999 ) {
		?>
		<tr>
			<th scope="row"><label for="tfm-tc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="number" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" id="tfm-tc-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" />
				<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Textarea row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Description.
	 * @param array  $s     Settings.
	 * @param int    $rows  Rows.
	 */
	private function textarea( $key, $label, $help, $s, $rows = 4 ) {
		?>
		<tr>
			<th scope="row"><label for="tfm-tc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<textarea class="large-text" rows="<?php echo esc_attr( $rows ); ?>" id="tfm-tc-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->name( $key ) ); ?>"><?php echo esc_textarea( $s[ $key ] ); ?></textarea>
				<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Select row.
	 *
	 * @param string $key     Key.
	 * @param string $label   Label.
	 * @param array  $s       Settings.
	 * @param array  $choices value => label.
	 */
	private function select( $key, $label, $s, $choices ) {
		?>
		<tr>
			<th scope="row"><label for="tfm-tc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="tfm-tc-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->name( $key ) ); ?>">
					<?php foreach ( $choices as $value => $choice_label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s[ $key ], $value ); ?>><?php echo esc_html( $choice_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Color picker row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param array  $s     Settings.
	 */
	private function color( $key, $label, $s ) {
		?>
		<tr>
			<th scope="row"><label for="tfm-tc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input type="text" class="tfm-tc-color" id="tfm-tc-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" /></td>
		</tr>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Consent log screen
	 * ------------------------------------------------------------------ */

	/**
	 * Render the consent log viewer.
	 */
	public function render_log_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tfm-tracking-consent' ) );
		}

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 50;
		$data     = TFM_TC_Consent_Log::query( $page, $per_page );
		$pages    = max( 1, (int) ceil( $data['total'] / $per_page ) );

		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=tfm_tc_export_log' ), 'tfm_tc_export_log' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Consent Log', 'tfm-tracking-consent' ); ?>
				<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'tfm-tracking-consent' ); ?></a>
			</h1>
			<p class="description">
				<?php
				/* translators: %d: total records */
				echo esc_html( sprintf( __( '%d consent receipts. IP addresses are stored only as salted one-way hashes.', 'tfm-tracking-consent' ), $data['total'] ) );
				?>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date (UTC)', 'tfm-tracking-consent' ); ?></th>
						<th><?php esc_html_e( 'Action', 'tfm-tracking-consent' ); ?></th>
						<th><?php esc_html_e( 'Version', 'tfm-tracking-consent' ); ?></th>
						<th><?php esc_html_e( 'Categories granted', 'tfm-tracking-consent' ); ?></th>
						<th><?php esc_html_e( 'Visitor hash', 'tfm-tracking-consent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $data['rows'] ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No consent receipts recorded yet.', 'tfm-tracking-consent' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $data['rows'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td><?php echo esc_html( $row['action'] ); ?></td>
								<td><?php echo esc_html( $row['consent_version'] ); ?></td>
								<td><?php echo esc_html( $this->format_categories( $row['categories'] ) ); ?></td>
								<td><code><?php echo esc_html( substr( $row['ip_hash'], 0, 12 ) ); ?>…</code></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php if ( $pages > 1 ) : ?>
				<p>
					<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
						<?php if ( $i === $page ) : ?>
							<strong><?php echo esc_html( $i ); ?></strong>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo esc_html( $i ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Human-readable granted category list from stored JSON.
	 *
	 * @param string $json Stored categories JSON.
	 * @return string
	 */
	private function format_categories( $json ) {
		$cats = json_decode( (string) $json, true );
		if ( ! is_array( $cats ) ) {
			return '—';
		}
		$granted = array_keys( array_filter( $cats ) );
		return $granted ? implode( ', ', $granted ) : __( 'none', 'tfm-tracking-consent' );
	}

	/**
	 * Stream the consent log as CSV.
	 */
	public function export_log_csv() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tfm-tracking-consent' ) );
		}
		check_admin_referer( 'tfm_tc_export_log' );

		global $wpdb;
		$table = TFM_TC_Consent_Log::table();
		$rows  = $wpdb->get_results( "SELECT created_at, consent_version, action, categories, ip_hash, user_agent FROM {$table} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tfm-consent-log-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'created_at_utc', 'consent_version', 'action', 'categories', 'ip_hash', 'user_agent' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
