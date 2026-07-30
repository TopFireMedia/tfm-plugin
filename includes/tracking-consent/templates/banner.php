<?php
/**
 * Consent banner + preferences panel markup.
 *
 * Override by copying to yourtheme/tfm-tracking-consent/banner.php.
 * Available variables: $settings (array), $categories (array),
 * $elementor_content (string, may be empty).
 *
 * @package TFM_Tracking_Consent
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="tfm-tc-root" class="tfm-tc-layout-<?php echo esc_attr( $settings['layout'] ); ?> tfm-tc-pos-<?php echo esc_attr( $settings['position'] ); ?>" hidden>

	<div class="tfm-tc-banner" role="dialog" aria-labelledby="tfm-tc-heading" aria-describedby="tfm-tc-message">
		<?php if ( ! empty( $elementor_content ) ) : ?>
			<div class="tfm-tc-elementor"><?php echo $elementor_content; // phpcs:ignore WordPress.Security.EscapeOutput -- Elementor renders and escapes its own template output. ?></div>
		<?php else : ?>
			<h2 class="tfm-tc-heading" id="tfm-tc-heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
			<p class="tfm-tc-message" id="tfm-tc-message">
				<?php echo wp_kses_post( $settings['message'] ); ?>
				<?php if ( ! empty( $settings['privacy_url'] ) ) : ?>
					<a href="<?php echo esc_url( $settings['privacy_url'] ); ?>" class="tfm-tc-privacy-link"><?php esc_html_e( 'Privacy Policy', 'tfm-tracking-consent' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<div class="tfm-tc-actions">
			<button type="button" class="tfm-tc-btn tfm-tc-btn-ghost" data-tfm-tc-action="preferences"><?php echo esc_html( $settings['btn_prefs'] ); ?></button>
			<button type="button" class="tfm-tc-btn tfm-tc-btn-ghost" data-tfm-tc-action="reject"><?php echo esc_html( $settings['btn_reject'] ); ?></button>
			<button type="button" class="tfm-tc-btn tfm-tc-btn-primary" data-tfm-tc-action="accept"><?php echo esc_html( $settings['btn_accept'] ); ?></button>
		</div>
	</div>

	<div class="tfm-tc-overlay" hidden>
		<div class="tfm-tc-modal" role="dialog" aria-modal="true" aria-labelledby="tfm-tc-prefs-heading">
			<button type="button" class="tfm-tc-close" data-tfm-tc-action="close" aria-label="<?php esc_attr_e( 'Close preferences', 'tfm-tracking-consent' ); ?>">&times;</button>
			<h2 class="tfm-tc-heading" id="tfm-tc-prefs-heading"><?php esc_html_e( 'Privacy Preferences', 'tfm-tracking-consent' ); ?></h2>
			<p class="tfm-tc-message"><?php esc_html_e( 'Choose which categories of cookies and similar technologies you allow. Your choice applies until you change it or it expires.', 'tfm-tracking-consent' ); ?></p>

			<div class="tfm-tc-categories">
				<?php foreach ( $categories as $slug => $label ) : ?>
					<div class="tfm-tc-category">
						<div class="tfm-tc-category-head">
							<span class="tfm-tc-category-label" id="tfm-tc-cat-label-<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></span>
							<?php if ( 'necessary' === $slug ) : ?>
								<span class="tfm-tc-always-on"><?php esc_html_e( 'Always active', 'tfm-tracking-consent' ); ?></span>
							<?php else : ?>
								<label class="tfm-tc-switch">
									<input type="checkbox" data-tfm-tc-category-toggle="<?php echo esc_attr( $slug ); ?>" aria-labelledby="tfm-tc-cat-label-<?php echo esc_attr( $slug ); ?>" />
									<span class="tfm-tc-slider" aria-hidden="true"></span>
								</label>
							<?php endif; ?>
						</div>
						<p class="tfm-tc-category-desc"><?php echo esc_html( $settings[ 'cat_desc_' . $slug ] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<p class="tfm-tc-gpc-note" data-tfm-tc-gpc-note hidden><?php esc_html_e( 'Your browser sent a Global Privacy Control signal, so Advertising and Personalization are off by default.', 'tfm-tracking-consent' ); ?></p>

			<div class="tfm-tc-actions">
				<button type="button" class="tfm-tc-btn tfm-tc-btn-ghost" data-tfm-tc-action="reject"><?php echo esc_html( $settings['btn_reject'] ); ?></button>
				<button type="button" class="tfm-tc-btn tfm-tc-btn-ghost" data-tfm-tc-action="accept"><?php echo esc_html( $settings['btn_accept'] ); ?></button>
				<button type="button" class="tfm-tc-btn tfm-tc-btn-primary" data-tfm-tc-action="save"><?php echo esc_html( $settings['btn_save'] ); ?></button>
			</div>
		</div>
	</div>
</div>
