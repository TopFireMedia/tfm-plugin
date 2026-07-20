<?php
namespace TFM\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget_Lead_Magnet extends Widget_Base {

	public function get_name() {
		return 'tfm-lead-magnet';
	}

	public function get_title() {
		return esc_html__( 'TFM Lead Magnet', 'topfiremedia' );
	}

	public function get_icon() {
		return 'eicon-image';
	}

	public function get_categories() {
		return [ 'tfm' ];
	}

	public function get_keywords() {
		return [ 'lead magnet', 'image', 'pdf', 'download', 'tfm' ];
	}

	protected function register_controls() {

		// ── Content ──────────────────────────────────────────────────────
		$this->start_controls_section(
			'section_content',
			[ 'label' => esc_html__( 'Content', 'topfiremedia' ) ]
		);

		// Status notice showing what is configured
		if ( function_exists( 'tfm_load_settings' ) ) {
			$plugin_settings = tfm_load_settings();
			$image_id        = absint( $plugin_settings['lead_magnet']['image_id'] ?? 0 );
			$file_id         = absint( $plugin_settings['lead_magnet']['file_id'] ?? 0 );
		} else {
			$image_id = 0;
			$file_id  = 0;
		}

		$this->add_control(
			'lm_notice',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => sprintf(
					'<div style="background:#1f2933;border:1px solid #323f4b;border-radius:4px;padding:10px 14px;font-size:12px;line-height:1.8;color:#f8fafc;">
						<span style="color:%s;">●</span> %s<br>
						<span style="color:%s;">●</span> %s<br>
						<span style="font-size:11px;color:#cbd5e1;margin-top:4px;display:block;">%s</span>
					</div>',
					esc_attr( $image_id ? '#059669' : '#d97706' ),
					esc_html( $image_id ? __( 'Image configured', 'topfiremedia' ) : __( 'No image configured', 'topfiremedia' ) ),
					esc_attr( $file_id ? '#059669' : '#d97706' ),
					esc_html( $file_id ? __( 'PDF file configured', 'topfiremedia' ) : __( 'No file configured', 'topfiremedia' ) ),
					esc_html__( 'Configure in TFM Custom Functions → Lead Magnet', 'topfiremedia' )
				),
			]
		);

		// Image size
		$this->add_control(
			'image_size',
			[
				'label'   => esc_html__( 'Image Size', 'topfiremedia' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'large',
				'options' => [
					'thumbnail'    => esc_html__( 'Thumbnail', 'topfiremedia' ),
					'medium'       => esc_html__( 'Medium', 'topfiremedia' ),
					'medium_large' => esc_html__( 'Medium Large', 'topfiremedia' ),
					'large'        => esc_html__( 'Large', 'topfiremedia' ),
					'full'         => esc_html__( 'Full', 'topfiremedia' ),
				],
			]
		);

		// Alt text
		$this->add_control(
			'alt_text',
			[
				'label'       => esc_html__( 'Alt Text', 'topfiremedia' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Industry Outlook', 'topfiremedia' ),
				'placeholder' => esc_html__( 'Enter image alt text', 'topfiremedia' ),
			]
		);

		// ── Link ─────────────────────────────────────────────────────────
		$this->add_control(
			'link_heading',
			[
				'label'     => esc_html__( 'Link', 'topfiremedia' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'enable_link',
			[
				'label'        => esc_html__( 'Link to PDF File', 'topfiremedia' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'topfiremedia' ),
				'label_off'    => esc_html__( 'No', 'topfiremedia' ),
				'return_value' => 'yes',
				'default'      => 'no',
			]
		);

		$this->add_control(
			'link_target',
			[
				'label'     => esc_html__( 'Open In', 'topfiremedia' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '_blank',
				'options'   => [
					'_blank' => esc_html__( 'New Tab', 'topfiremedia' ),
					'_self'  => esc_html__( 'Same Tab', 'topfiremedia' ),
				],
				'condition' => [ 'enable_link' => 'yes' ],
			]
		);

		// ── Alignment ────────────────────────────────────────────────────
		$this->add_responsive_control(
			'align',
			[
				'label'     => esc_html__( 'Alignment', 'topfiremedia' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'   => [ 'title' => esc_html__( 'Left', 'topfiremedia' ),   'icon' => 'eicon-text-align-left' ],
					'center' => [ 'title' => esc_html__( 'Center', 'topfiremedia' ), 'icon' => 'eicon-text-align-center' ],
					'right'  => [ 'title' => esc_html__( 'Right', 'topfiremedia' ),  'icon' => 'eicon-text-align-right' ],
				],
				'default'   => 'left',
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}} .tfm-lead-magnet-wrapper' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		// ── Style: Image ─────────────────────────────────────────────────
		$this->start_controls_section(
			'section_style_image',
			[
				'label' => esc_html__( 'Image', 'topfiremedia' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'image_width',
			[
				'label'      => esc_html__( 'Width', 'topfiremedia' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'vw' ],
				'range'      => [
					'px' => [ 'min' => 1, 'max' => 1400 ],
					'%'  => [ 'min' => 1, 'max' => 100 ],
				],
				'selectors'  => [
					'{{WRAPPER}} .tfm-lead-magnet-image' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'image_max_width',
			[
				'label'      => esc_html__( 'Max Width', 'topfiremedia' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'vw' ],
				'range'      => [
					'px' => [ 'min' => 1, 'max' => 1400 ],
					'%'  => [ 'min' => 1, 'max' => 100 ],
				],
				'selectors'  => [
					'{{WRAPPER}} .tfm-lead-magnet-image' => 'max-width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		// Normal / Hover tabs
		$this->start_controls_tabs( 'image_effects' );

		$this->start_controls_tab( 'image_normal', [ 'label' => esc_html__( 'Normal', 'topfiremedia' ) ] );

		$this->add_control(
			'image_opacity',
			[
				'label'     => esc_html__( 'Opacity', 'topfiremedia' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 1, 'step' => 0.01 ] ],
				'selectors' => [ '{{WRAPPER}} .tfm-lead-magnet-image' => 'opacity: {{SIZE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			[
				'name'     => 'image_css_filters',
				'selector' => '{{WRAPPER}} .tfm-lead-magnet-image',
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab( 'image_hover', [ 'label' => esc_html__( 'Hover', 'topfiremedia' ) ] );

		$this->add_control(
			'image_opacity_hover',
			[
				'label'     => esc_html__( 'Opacity', 'topfiremedia' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 1, 'step' => 0.01 ] ],
				'selectors' => [ '{{WRAPPER}} .tfm-lead-magnet-wrapper:hover .tfm-lead-magnet-image' => 'opacity: {{SIZE}};' ],
			]
		);

		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			[
				'name'     => 'image_css_filters_hover',
				'selector' => '{{WRAPPER}} .tfm-lead-magnet-wrapper:hover .tfm-lead-magnet-image',
			]
		);

		$this->add_control(
			'image_hover_transition',
			[
				'label'     => esc_html__( 'Transition Duration (s)', 'topfiremedia' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 3, 'step' => 0.1 ] ],
				'selectors' => [ '{{WRAPPER}} .tfm-lead-magnet-image' => 'transition: all {{SIZE}}s;' ],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		// Border / radius / shadow
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name'      => 'image_border',
				'selector'  => '{{WRAPPER}} .tfm-lead-magnet-image',
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'image_border_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'topfiremedia' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .tfm-lead-magnet-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'image_box_shadow',
				'selector' => '{{WRAPPER}} .tfm-lead-magnet-image',
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! function_exists( 'tfm_load_settings' ) ) {
			return;
		}

		$plugin_settings = tfm_load_settings();
		$image_id        = absint( $plugin_settings['lead_magnet']['image_id'] ?? 0 );
		$file_id         = absint( $plugin_settings['lead_magnet']['file_id'] ?? 0 );

		if ( ! $image_id ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding:20px;background:#f9f9f9;border:1px dashed #ccc;text-align:center;color:#999;font-size:13px;">'
					. esc_html__( 'No lead magnet image configured. Set one in TFM Custom Functions → Lead Magnet.', 'topfiremedia' )
					. '</div>';
			}
			return;
		}

		$image_size = ! empty( $settings['image_size'] ) ? $settings['image_size'] : 'large';
		$alt_text   = ! empty( $settings['alt_text'] )   ? $settings['alt_text']   : __( 'Industry Outlook', 'topfiremedia' );
		$is_link    = ( $settings['enable_link'] === 'yes' ) && $file_id;

		$img = wp_get_attachment_image( $image_id, $image_size, false, [
			'class' => 'tfm-lead-magnet-image',
			'alt'   => esc_attr( $alt_text ),
		] );

		if ( ! $img ) {
			return;
		}

		$this->add_render_attribute( 'wrapper', 'class', 'tfm-lead-magnet-wrapper' );

		echo '<div ' . $this->get_render_attribute_string( 'wrapper' ) . '>';

		if ( $is_link ) {
			$file_url = wp_get_attachment_url( $file_id );
			if ( $file_url ) {
				$target = ! empty( $settings['link_target'] ) ? $settings['link_target'] : '_blank';
				printf(
					'<a href="%s" target="%s" rel="noopener">%s</a>',
					esc_url( $file_url ),
					esc_attr( $target ),
					$img
				);
			} else {
				echo $img;
			}
		} else {
			echo $img;
		}

		echo '</div>';
	}
}
