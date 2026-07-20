<?php
namespace TFM\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Icons_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class Widget_Email extends Widget_Base {
    
    public function get_name() {
        return 'tfm-email';
    }
    
    public function get_title() {
        return esc_html__('TFM Email', 'topfiremedia');
    }
    
    public function get_icon() {
        return 'eicon-envelope';
    }
    
    public function get_categories() {
        return ['tfm'];
    }
    
    public function get_keywords() {
        return ['email', 'mail', 'contact', 'tfm'];
    }
    
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Content', 'topfiremedia'),
            ]
        );
        
        $current_email = $this->get_default_email();
        $this->add_control(
            'email_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf(
                    '<div style="background:#1f2933;border:1px solid #323f4b;border-radius:4px;padding:10px 14px;font-size:12px;line-height:1.5;color:#f8fafc;">%s <strong style="color:#f4faff;">%s</strong></div>',
                    esc_html__('Using saved email address:', 'topfiremedia'),
                    $current_email ? esc_html($current_email) : esc_html__('Not configured', 'topfiremedia')
                ),
            ]
        );
        
        $this->add_control(
            'enable_link',
            [
                'label' => esc_html__('Enable Click-to-Email', 'topfiremedia'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'topfiremedia'),
                'label_off' => esc_html__('No', 'topfiremedia'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'selected_icon',
            [
                'label' => esc_html__('Icon', 'topfiremedia'),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-envelope',
                    'library' => 'fa-solid',
                ],
            ]
        );
        
        $start = is_rtl() ? 'right' : 'left';
        $end = is_rtl() ? 'left' : 'right';
        
        $this->add_control(
            'icon_align',
            [
                'label' => esc_html__('Icon Position', 'topfiremedia'),
                'type' => Controls_Manager::CHOOSE,
                'default' => is_rtl() ? 'row-reverse' : 'row',
                'options' => [
                    'row' => [
                        'title' => esc_html__('Start', 'topfiremedia'),
                        'icon' => "eicon-h-align-{$start}",
                    ],
                    'row-reverse' => [
                        'title' => esc_html__('End', 'topfiremedia'),
                        'icon' => "eicon-h-align-{$end}",
                    ],
                ],
                'selectors_dictionary' => [
                    'left' => is_rtl() ? 'row-reverse' : 'row',
                    'right' => is_rtl() ? 'row' : 'row-reverse',
                ],
                'selectors' => [
                    '{{WRAPPER}} .tfm-email-content-wrapper' => 'flex-direction: {{VALUE}};',
                ],
                'condition' => [
                    'selected_icon[value]!' => '',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'align',
            [
                'label' => esc_html__('Alignment', 'topfiremedia'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'topfiremedia'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'topfiremedia'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'topfiremedia'),
                        'icon' => 'eicon-text-align-right',
                    ],
                    'justify' => [
                        'title' => esc_html__('Justify', 'topfiremedia'),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .tfm-email-wrapper' => 'text-align: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Text
        $this->start_controls_section(
            'section_style_text',
            [
                'label' => esc_html__('Text', 'topfiremedia'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'text_color',
            [
                'label' => esc_html__('Text Color', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .tfm-email-text' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'text_typography',
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
                'selector' => '{{WRAPPER}} .tfm-email-text',
            ]
        );
        
        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'text_shadow',
                'selector' => '{{WRAPPER}} .tfm-email-text',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Icon
        $this->start_controls_section(
            'section_style_icon',
            [
                'label' => esc_html__('Icon', 'topfiremedia'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'selected_icon[value]!' => '',
                ],
            ]
        );
        
        $this->start_controls_tabs('icon_colors');
        
        $this->start_controls_tab(
            'icon_colors_normal',
            [
                'label' => esc_html__('Normal', 'topfiremedia'),
            ]
        );
        
        $this->add_control(
            'icon_color',
            [
                'label' => esc_html__('Icon Color', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .tfm-email-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .tfm-email-icon svg' => 'fill: {{VALUE}};',
                ],
                'global' => [
                    'default' => Global_Colors::COLOR_PRIMARY,
                ],
            ]
        );
        
        $this->end_controls_tab();
        
        $this->start_controls_tab(
            'icon_colors_hover',
            [
                'label' => esc_html__('Hover', 'topfiremedia'),
            ]
        );
        
        $this->add_control(
            'icon_hover_color',
            [
                'label' => esc_html__('Icon Color', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tfm-email-wrapper:hover .tfm-email-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .tfm-email-wrapper:hover .tfm-email-icon svg' => 'fill: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_tab();
        
        $this->end_controls_tabs();
        
        $this->add_responsive_control(
            'icon_size',
            [
                'label' => esc_html__('Icon Size', 'topfiremedia'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 6,
                        'max' => 300,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .tfm-email-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .tfm-email-icon svg' => 'height: {{SIZE}}{{UNIT}};',
                ],
                'separator' => 'before',
            ]
        );
        
        $this->add_control(
            'icon_spacing',
            [
                'label' => esc_html__('Icon Spacing', 'topfiremedia'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .tfm-email-content-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'selected_icon[value]!' => '',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'icon_padding',
            [
                'label' => esc_html__('Icon Padding', 'topfiremedia'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em', 'rem'],
                'selectors' => [
                    '{{WRAPPER}} .tfm-email-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'icon_border',
                'selector' => '{{WRAPPER}} .tfm-email-icon',
            ]
        );
        
        $this->add_control(
            'icon_border_radius',
            [
                'label' => esc_html__('Border Radius', 'topfiremedia'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .tfm-email-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'icon_box_shadow',
                'selector' => '{{WRAPPER}} .tfm-email-icon',
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Get email address
        $email = $this->get_default_email();
        if (!$email) {
            return;
        }
        
        // Prepare outer wrapper attributes
        $this->add_render_attribute('outer-wrapper', 'class', 'tfm-email-wrapper');
        
        // Determine if link is enabled
        $is_link = $settings['enable_link'] === 'yes';
        $link_tag = $is_link ? 'a' : 'span';
        
        // Prepare link/container attributes
        $this->add_render_attribute('link', 'class', 'tfm-email-content-wrapper');
        if ($is_link) {
            $this->add_render_attribute('link', 'class', 'tfm-email-link');
            $this->add_render_attribute('link', 'href', 'mailto:' . esc_attr($email));
        }
        
        // Prepare text attributes
        $this->add_render_attribute('text', 'class', 'tfm-email-text');
        
        // Prepare icon attributes
        $this->add_render_attribute('icon', 'class', 'tfm-email-icon');
        
        $has_icon = !empty($settings['selected_icon']['value']);
        $migrated = isset($settings['__fa4_migrated']['selected_icon']);
        $is_new = empty($settings['icon']) && Icons_Manager::is_migration_allowed();
        ?>
        <div <?php $this->print_render_attribute_string('outer-wrapper'); ?>>
            <<?php echo esc_html($link_tag); ?> <?php $this->print_render_attribute_string('link'); ?>>
                <?php if ($has_icon) : ?>
                <span <?php $this->print_render_attribute_string('icon'); ?>>
                    <?php if ($is_new || $migrated) {
                        Icons_Manager::render_icon($settings['selected_icon'], ['aria-hidden' => 'true']);
                    } else if (!empty($settings['icon'])) { ?>
                        <i class="<?php echo esc_attr($settings['icon']); ?>" aria-hidden="true"></i>
                    <?php } ?>
                </span>
                <?php endif; ?>
                <span <?php $this->print_render_attribute_string('text'); ?>><?php echo esc_html($email); ?></span>
            </<?php echo esc_html($link_tag); ?>>
        </div>
        <?php
    }
    
    private function get_default_email() {
        if (!function_exists('tfm_load_settings')) {
            return '';
        }
        
        $settings = tfm_load_settings();
        return $settings['email'] ?? '';
    }
}
