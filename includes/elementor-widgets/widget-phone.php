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

class Widget_Phone extends Widget_Base {
    
    public function get_name() {
        return 'tfm-phone';
    }
    
    public function get_title() {
        return esc_html__('TFM Phone', 'topfiremedia');
    }
    
    public function get_icon() {
        return 'fa fa-phone';
    }
    
    public function get_categories() {
        return ['tfm'];
    }
    
    public function get_keywords() {
        return ['phone', 'tel', 'contact', 'tfm'];
    }
    
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Content', 'topfiremedia'),
            ]
        );
        
        $current_phone = $this->get_default_phone();
        $formatted_display = $current_phone ? $this->format_phone($current_phone) : esc_html__('Not configured', 'topfiremedia');
        $this->add_control(
            'phone_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf(
                    '<div style="background:#1f2933;border:1px solid #323f4b;border-radius:4px;padding:10px 14px;font-size:12px;line-height:1.5;color:#f8fafc;">%s <strong style="color:#f4faff;">%s</strong><br><span style="font-size:11px;color:#cbd5e1;margin-top:4px;display:block;">%s</span></div>',
                    esc_html__('Using saved phone number:', 'topfiremedia'),
                    esc_html($formatted_display),
                    esc_html__('Format can be changed in TFM Custom Functions → Contact Information', 'topfiremedia')
                ),
            ]
        );
        
        $this->add_control(
            'enable_link',
            [
                'label' => esc_html__('Enable Click-to-Call', 'topfiremedia'),
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
                    'value' => 'fas fa-phone',
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
                    '{{WRAPPER}} .tfm-phone-content-wrapper' => 'flex-direction: {{VALUE}};',
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
                    '{{WRAPPER}} .tfm-phone-wrapper' => 'text-align: {{VALUE}};',
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
                    '{{WRAPPER}} .tfm-phone-text' => 'color: {{VALUE}};',
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
                'selector' => '{{WRAPPER}} .tfm-phone-text',
            ]
        );
        
        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'text_shadow',
                'selector' => '{{WRAPPER}} .tfm-phone-text',
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
                    '{{WRAPPER}} .tfm-phone-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .tfm-phone-icon svg' => 'fill: {{VALUE}};',
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
                    '{{WRAPPER}} .tfm-phone-wrapper:hover .tfm-phone-icon' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .tfm-phone-wrapper:hover .tfm-phone-icon svg' => 'fill: {{VALUE}};',
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
                    '{{WRAPPER}} .tfm-phone-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .tfm-phone-icon svg' => 'height: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .tfm-phone-content-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .tfm-phone-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'icon_border',
                'selector' => '{{WRAPPER}} .tfm-phone-icon',
            ]
        );
        
        $this->add_control(
            'icon_border_radius',
            [
                'label' => esc_html__('Border Radius', 'topfiremedia'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .tfm-phone-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'icon_box_shadow',
                'selector' => '{{WRAPPER}} .tfm-phone-icon',
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Get phone number
        $phone = $this->get_default_phone();
        if (!$phone) {
            return;
        }

        $formatted_phone = $this->format_phone($phone);
        
        // Prepare outer wrapper attributes
        $this->add_render_attribute('outer-wrapper', 'class', 'tfm-phone-wrapper');
        
        // Determine if link is enabled
        $is_link = $settings['enable_link'] === 'yes';
        $link_tag = $is_link ? 'a' : 'span';
        
        // Prepare link/container attributes
        $this->add_render_attribute('link', 'class', 'tfm-phone-content-wrapper');
        if ($is_link) {
            $this->add_render_attribute('link', 'class', 'tfm-phone-link');
            $this->add_render_attribute('link', 'href', $this->get_tel_link($phone));
        }
        
        // Prepare text attributes
        $this->add_render_attribute('text', 'class', 'tfm-phone-text');
        
        // Prepare icon attributes
        $this->add_render_attribute('icon', 'class', 'tfm-phone-icon');
        
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
                <span <?php $this->print_render_attribute_string('text'); ?>><?php echo esc_html($formatted_phone); ?></span>
            </<?php echo esc_html($link_tag); ?>>
        </div>
        <?php
    }
    
    private function get_default_phone() {
        if (!function_exists('tfm_load_settings')) {
            return '';
        }
        
        $settings = tfm_load_settings();
        $raw_phone = preg_replace('/\D/', '', $settings['phone'] ?? '');
        
        if (strlen($raw_phone) === 10) {
            return $raw_phone; // Return raw phone for formatting
        }
        
        return '';
    }
    
    private function format_phone($phone) {
        // If phone is already formatted, extract raw digits
        $raw = preg_replace('/\D/', '', $phone);
        
        if (strlen($raw) !== 10) {
            return $phone; // Return as-is if invalid
        }
        
        // Get format from settings (default to format 4 for backward compatibility)
        if (!function_exists('tfm_load_settings')) {
            // Fallback to format 4 if settings function doesn't exist
            return substr($raw, 0, 3) . '-' . substr($raw, 3, 3) . '-' . substr($raw, 6);
        }
        
        $settings = tfm_load_settings();
        $format = isset($settings['phone_format']) ? $settings['phone_format'] : '4';
        
        // Format based on selected option
        switch ($format) {
            case '1': // +1 (xxx) xxx-xxxx
                return '+1 (' . substr($raw, 0, 3) . ') ' . substr($raw, 3, 3) . '-' . substr($raw, 6);
            case '2': // +1-xxx-xxx-xxxx
                return '+1-' . substr($raw, 0, 3) . '-' . substr($raw, 3, 3) . '-' . substr($raw, 6);
            case '3': // (xxx) xxx-xxxx
                return '(' . substr($raw, 0, 3) . ') ' . substr($raw, 3, 3) . '-' . substr($raw, 6);
            case '4': // xxx-xxx-xxxx
            default:
                return substr($raw, 0, 3) . '-' . substr($raw, 3, 3) . '-' . substr($raw, 6);
        }
    }
    
    private function get_tel_link($phone) {
        $raw = preg_replace('/\D/', '', $phone);
        
        if (strlen($raw) === 10) {
            return 'tel:+1' . $raw;
        }
        
        return 'tel:' . $raw;
    }
}
