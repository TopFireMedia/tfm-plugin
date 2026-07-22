<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if Elementor is active
if (!class_exists('Elementor\Widget_Base')) {
    return;
}

class Elementor_PRM_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'press_release_grid';
    }

    public function get_title() {
        return 'Press Release Grid';
    }

    public function get_icon() {
        return 'eicon-posts-grid';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => 'Content',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => 'Posts Per Page',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 6,
            ]
        );

        $this->add_control(
            'show_pagination',
            [
                'label' => 'Show Pagination',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Show',
                'label_off' => 'Hide',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'default_sort',
            [
                'label' => 'Default Sort Order',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'date_desc',
                'options' => [
                    'date_desc' => 'Date (Newest First)',
                    'date_asc' => 'Date (Oldest First)',
                    // Removed title and source sorting options
                ],
            ]
        );

        $this->add_control(
            'show_sort_controls',
            [
                'label' => 'Show Sort Controls',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Show',
                'label_off' => 'Hide',
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => 'Allow users to change sort order',
            ]
        );

        $this->add_control(
            'sort_options',
            [
                'label' => 'Available Sort Options',
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'date_desc' => 'Date (Newest First)',
                    'date_asc' => 'Date (Oldest First)',
                    // Removed title and source sorting options
                ],
                'default' => ['date_desc', 'date_asc'], // Updated default
                'condition' => [
                    'show_sort_controls' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => 'Layout',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => 'Grid',
                    'list' => 'List',
                ],
            ]
        );

        $this->add_control(
            'show_excerpt',
            [
                'label' => 'Show Excerpt',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Show',
                'label_off' => 'Hide',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'excerpt_length',
            [
                'label' => 'Excerpt Length (words)',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 20,
                'min' => 5,
                'max' => 100,
                'condition' => [
                    'show_excerpt' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_source',
            [
                'label' => 'Show Source',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Show',
                'label_off' => 'Hide',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_date',
            [
                'label' => 'Show Date',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Show',
                'label_off' => 'Hide',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'meta_alignment',
            [
                'label' => 'Meta Alignment',
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => 'Left',
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => 'Center',
                        'icon' => 'eicon-text-align-center',
                    ],
                    'flex-end' => [
                        'title' => 'Right',
                        'icon' => 'eicon-text-align-right',
                    ],
                    'space-between' => [
                        'title' => 'Space Between',
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'default' => 'space-between',
                'toggle' => true,
                'selectors' => [
                    '{{WRAPPER}} .prm-card-meta' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Responsive Columns Section
        $this->start_controls_section(
            'columns_section',
            [
                'label' => 'Responsive Columns',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label' => 'Columns',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => [
                    '1' => '1 Column',
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                    '5' => '5 Columns',
                    '6' => '6 Columns',
                ],
                'condition' => [
                    'layout' => 'grid',
                ],
                'frontend_available' => true,
                'selectors' => [
                    '{{WRAPPER}} .prm-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr)',
                ],
            ]
        );

        //Notice that respospnsive controls not available in list mode
        $this->add_control(
            'list_columns_notice',
            [
                'label' => '',
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div style="background: #f8f8f8; padding: 10px; border-radius: 4px; border-left: 4px solid #007cba; color: #000000; font-weight: 500;">Columns setting is only available for Grid layout. List layout always displays items in a single column.</div>',
                'condition' => [
                    'layout' => 'list',
                ],
            ]
        );

        $this->end_controls_section();

        // Image Section
        $this->start_controls_section(
            'image_section',
            [
                'label' => 'Image Settings',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'image_aspect_ratio',
            [
                'label' => 'Image Aspect Ratio',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'landscape',
                'options' => [
                    'landscape' => 'Landscape (4:3)',
                    'square' => 'Square (1:1)',
                    'portrait' => 'Portrait (3:4)',
                    'custom' => 'Custom Ratio',
                ],
            ]
        );

        $this->add_control(
            'custom_aspect_ratio',
            [
                'label' => 'Custom Aspect Ratio',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '16/9',
                'description' => 'Enter as width/height (e.g., 16/9, 4/3, 1/1)',
                'condition' => [
                    'image_aspect_ratio' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'image_size',
            [
                'label' => 'Image Size',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'medium',
                'options' => [
                    'thumbnail' => 'Thumbnail',
                    'medium' => 'Medium',
                    'large' => 'Large',
                    'full' => 'Full',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => 'Layout & Colors',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_background',
            [
                'label' => 'Card Background',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-card' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'card_padding',
            [
                'label' => 'Card Padding',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'card_border_radius',
            [
                'label' => 'Border Radius',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .prm-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .prm-card',
            ]
        );

        $this->end_controls_section();

        // Sort Controls Style Section
        $this->start_controls_section(
            'sort_controls_section',
            [
                'label' => 'Sort Controls',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_sort_controls' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'sort_controls_alignment',
            [
                'label' => 'Alignment',
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => 'Left',
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => 'Center',
                        'icon' => 'eicon-text-align-center',
                    ],
                    'flex-end' => [
                        'title' => 'Right',
                        'icon' => 'eicon-text-align-right',
                    ],
                    'space-between' => [
                        'title' => 'Space Between',
                        'icon' => 'eicon-text-align-justify',
                    ],
                    'stretch' => [
                        'title' => 'Full Width',
                        'icon' => 'eicon-justify-stretch-h',
                    ],
                ],
                'default' => 'flex-end',
                'tablet_default' => 'flex-end',
                'mobile_default' => 'stretch',
                'toggle' => true,
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-controls' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'sort_controls_layout',
            [
                'label' => 'Layout',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'inline',
                'tablet_default' => 'inline',
                'mobile_default' => 'stack',
                'options' => [
                    'inline' => 'Inline',
                    'stack' => 'Stacked',
                ],
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-controls' => '{{VALUE}}',
                ],
                'selectors_dictionary' => [
                    'inline' => 'display: flex; align-items: center; flex-wrap: wrap;',
                    'stack' => 'display: block;',
                ],
            ]
        );

        $this->add_responsive_control(
            'sort_controls_text_align',
            [
                'label' => 'Text Alignment',
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => 'Left',
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => 'Center',
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => 'Right',
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'tablet_default' => 'left',
                'mobile_default' => 'center',
                'toggle' => true,
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-controls' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sort_label_color',
            [
                'label' => 'Label Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sort_select_color',
            [
                'label' => 'Select Text Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sort_select_bg_color',
            [
                'label' => 'Select Background Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'sort_select_border_color',
            [
                'label' => 'Select Border Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-select' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'sort_select_width',
            [
                'label' => 'Select Width',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em'],
                'range' => [
                    'px' => [
                        'min' => 100,
                        'max' => 400,
                        'step' => 1,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 5,
                        'max' => 30,
                        'step' => 0.5,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 200,
                ],
                'tablet_default' => [
                    'unit' => 'px',
                    'size' => 200,
                ],
                'mobile_default' => [
                    'unit' => '%',
                    'size' => 100,
                ],
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-select' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'sort_label_typography',
                'selector' => '{{WRAPPER}} .prm-sort-label',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'sort_select_typography',
                'selector' => '{{WRAPPER}} .prm-sort-select',
            ]
        );

        $this->add_responsive_control(
            'sort_label_spacing',
            [
                'label' => 'Label Spacing',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 3,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'tablet_default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'mobile_default' => [
                    'unit' => 'px',
                    'size' => 0,
                ],
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-label' => 'margin-right: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'sort_controls_layout' => 'inline',
                ],
            ]
        );

        $this->add_responsive_control(
            'sort_controls_margin',
            [
                'label' => 'Container Margin',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-controls' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'sort_controls_padding',
            [
                'label' => 'Container Padding',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-controls' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'sort_select_padding',
            [
                'label' => 'Select Padding',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'sort_select_border_radius',
            [
                'label' => 'Select Border Radius',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'sort_select_border_width',
            [
                'label' => 'Select Border Width',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px'],
                'selectors' => [
                    '{{WRAPPER}} .prm-sort-select' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // List Layout Image Section
        $this->start_controls_section(
            'list_image_section',
            [
                'label' => 'List Layout - Image Positioning',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'layout' => 'list',
                ],
            ]
        );

        $this->add_control(
            'list_image_position',
            [
                'label' => 'Image Position',
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'row' => [
                        'title' => 'Left',
                        'icon' => 'eicon-h-align-left',
                    ],
                    'row-reverse' => [
                        'title' => 'Right',
                        'icon' => 'eicon-h-align-right',
                    ],
                ],
                'default' => 'row',
                'toggle' => true,
                'selectors' => [
                    '{{WRAPPER}} .prm-layout-list .prm-card' => 'flex-direction: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'list_image_width',
            [
                'label' => 'Image Width',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em'],
                'range' => [
                    'px' => [
                        'min' => 100,
                        'max' => 500,
                        'step' => 1,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 50,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 5,
                        'max' => 30,
                        'step' => 0.5,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 200,
                ],
                'selectors' => [
                    '{{WRAPPER}} .prm-layout-list .prm-card-image' => 'flex: 0 0 {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'list_image_height',
            [
                'label' => 'Image Height',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 100,
                        'max' => 500,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 5,
                        'max' => 30,
                        'step' => 0.5,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 150,
                ],
                'selectors' => [
                    '{{WRAPPER}} .prm-layout-list .prm-card-image' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'list_image_spacing',
            [
                'label' => 'Image Spacing',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 5,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .prm-layout-list .prm-card' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'list_image_align_self',
            [
                'label' => 'Image Vertical Alignment',
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => 'Top',
                        'icon' => 'eicon-v-align-top',
                    ],
                    'center' => [
                        'title' => 'Middle',
                        'icon' => 'eicon-v-align-middle',
                    ],
                    'flex-end' => [
                        'title' => 'Bottom',
                        'icon' => 'eicon-v-align-bottom',
                    ],
                    'stretch' => [
                        'title' => 'Stretch',
                        'icon' => 'eicon-v-align-stretch',
                    ],
                ],
                'default' => 'center',
                'toggle' => true,
                'selectors' => [
                    '{{WRAPPER}} .prm-layout-list .prm-card-image' => 'align-self: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'list_image_object_fit',
            [
                'label' => 'Image Fit',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'cover',
                'options' => [
                    'cover' => 'Cover',
                    'contain' => 'Contain',
                    'fill' => 'Fill',
                ],
                'selectors' => [
                    '{{WRAPPER}} .prm-layout-list .prm-card-image img' => 'object-fit: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'list_image_border_radius',
            [
                'label' => 'Image Border Radius',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .prm-layout-list .prm-card-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .prm-layout-list .prm-card-image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Title Typography Section
        $this->start_controls_section(
            'title_typography_section',
            [
                'label' => 'Title Typography',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .prm-card-title, {{WRAPPER}} .prm-card-title a',
                'exclude' => ['line_height'], // Optional: exclude specific typography controls
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => 'Title Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-card-title a' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .prm-card-title' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'title_hover_color',
            [
                'label' => 'Title Hover Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-card-title a:hover' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .prm-card-title',
            ]
        );

        $this->add_control(
            'title_margin',
            [
                'label' => 'Title Margin',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-card-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Excerpt Typography Section
        $this->start_controls_section(
            'excerpt_typography_section',
            [
                'label' => 'Excerpt Typography',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'excerpt_color',
            [
                'label' => 'Excerpt Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-card-excerpt' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'excerpt_typography',
                'selector' => '{{WRAPPER}} .prm-card-excerpt',
            ]
        );

        $this->add_control(
            'excerpt_margin',
            [
                'label' => 'Excerpt Margin',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-card-excerpt' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Meta Typography Section
        $this->start_controls_section(
            'meta_typography_section',
            [
                'label' => 'Meta Text Typography',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'meta_color',
            [
                'label' => 'Meta Text Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-card-meta' => 'color: {{VALUE}}',
                ],
            ]
        );

        // Single typography control for all meta text
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'meta_typography',
                'selector' => '{{WRAPPER}} .prm-card-meta',
            ]
        );

        $this->add_control(
            'source_color',
            [
                'label' => 'Source Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-source' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'date_color',
            [
                'label' => 'Date Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-date' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'meta_margin',
            [
                'label' => 'Meta Margin',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-card-meta' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'meta_padding',
            [
                'label' => 'Meta Padding',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-card-meta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'meta_border_top',
            [
                'label' => 'Meta Border Top',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-card-meta' => 'border-top-width: {{TOP}}{{UNIT}}; border-top-style: solid;',
                ],
            ]
        );

        $this->add_control(
            'meta_border_color',
            [
                'label' => 'Meta Border Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-card-meta' => 'border-top-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();


        // Grid Layout Section
        $this->start_controls_section(
            'grid_section',
            [
                'label' => 'Grid Layout',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'grid_gap',
            [
                'label' => 'Grid Gap',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 5,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'tablet_default' => [
                    'unit' => 'px',
                    'size' => 15,
                ],
                'mobile_default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .prm-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'image_border_radius',
            [
                'label' => 'Image Border Radius',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .prm-card-image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Pagination Section
        $this->start_controls_section(
            'pagination_section',
            [
                'label' => 'Pagination',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_pagination' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'pagination_alignment',
            [
                'label' => 'Alignment',
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => 'Left',
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => 'Center',
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => 'Right',
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'center',
                'toggle' => true,
                'selectors' => [
                    '{{WRAPPER}} .prm-pagination' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_color',
            [
                'label' => 'Text Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-pagination a, {{WRAPPER}} .prm-pagination span' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_bg_color',
            [
                'label' => 'Background Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-pagination a, {{WRAPPER}} .prm-pagination span' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_active_color',
            [
                'label' => 'Active Text Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-pagination .current' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_active_bg_color',
            [
                'label' => 'Active Background Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .prm-pagination .current' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'pagination_typography',
                'selector' => '{{WRAPPER}} .prm-pagination a, {{WRAPPER}} .prm-pagination span',
            ]
        );

        $this->add_control(
            'pagination_padding',
            [
                'label' => 'Padding',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-pagination a, {{WRAPPER}} .prm-pagination span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_margin',
            [
                'label' => 'Margin',
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .prm-pagination' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Get current page for pagination
        $paged = max(1, get_query_var('paged'), get_query_var('page'));

        // Get sort parameters
        $current_sort = isset($_GET['prm_sort']) ? sanitize_text_field($_GET['prm_sort']) : $settings['default_sort'];

        // Build query args based on sort
        $args = array(
            'post_type' => 'press_release',
            'posts_per_page' => $settings['posts_per_page'],
            'paged' => $paged,
            'meta_query' => array(
                array(
                    'key' => 'release_date',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        // Apply sorting
        $this->apply_sorting($args, $current_sort);

        $query = new WP_Query($args);

        // Get responsive columns data
        $desktop_columns = $settings['columns'] ?? '3';
        $tablet_columns = $settings['columns_tablet'] ?? '2';
        $mobile_columns = $settings['columns_mobile'] ?? '1';

        echo '<div class="prm-press-releases">';

        // Render sort controls if enabled
        if ($settings['show_sort_controls'] === 'yes') {
            $this->render_sort_controls($settings, $current_sort);
        }

        // In your render() method, make sure this part exists:
        echo '<div class="prm-grid prm-layout-' . esc_attr($settings['layout']) . '"
                  data-columns="' . esc_attr($desktop_columns) . '"
                  data-columns-tablet="' . esc_attr($tablet_columns) . '"
                  data-columns-mobile="' . esc_attr($mobile_columns) . '">';

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_card($settings);
            }
            wp_reset_postdata();
        } else {
            echo '<p>No press releases found.</p>';
        }

        echo '</div>';

        // Render pagination
        if ($settings['show_pagination'] === 'yes' && $query->max_num_pages > 1) {
            $this->render_pagination($query, $paged, $current_sort);
        }

        echo '</div>';

        $this->render_custom_css($settings);
    }

    private function apply_sorting(&$args, $sort) {
        switch ($sort) {
            case 'date_asc':
                $args['meta_key'] = 'release_date';
                $args['orderby'] = 'meta_value';
                $args['order'] = 'ASC';
                break;

            case 'date_desc':
            default:
                $args['meta_key'] = 'release_date';
                $args['orderby'] = 'meta_value';
                $args['order'] = 'DESC';
                break;
        }
    }

    private function render_sort_controls($settings, $current_sort) {
        $sort_options = $settings['sort_options'] ?: ['date_desc', 'date_asc'];

        $sort_labels = [
            'date_desc' => 'Date (Newest First)',
            'date_asc' => 'Date (Oldest First)',
        ];

        echo '<div class="prm-sort-controls">';
        echo '<label class="prm-sort-label">Sort by: </label>';
        echo '<select class="prm-sort-select" onchange="window.location.href = this.value">';

        foreach ($sort_options as $option) {
            if (isset($sort_labels[$option])) {
                $url = add_query_arg('prm_sort', $option);
                $selected = $current_sort === $option ? 'selected' : '';
                echo '<option value="' . esc_url($url) . '" ' . $selected . '>' . esc_html($sort_labels[$option]) . '</option>';
            }
        }

        echo '</select>';
        echo '</div>';
    }

    private function render_card($settings) {
        $post_id = get_the_ID();
        $external_url = get_field('external_url', $post_id);
        $thumbnail = get_the_post_thumbnail($post_id, $settings['image_size']);
        $aspect_ratio_class = 'prm-aspect-' . $settings['image_aspect_ratio'];

        $show_source = $settings['show_source'] === 'yes' && get_field('source_name', $post_id);
        $show_date = $settings['show_date'] === 'yes' && get_field('release_date', $post_id);
        $has_meta = $show_source || $show_date;
        ?>
        <div class="prm-card">
            <?php if ($thumbnail && $external_url) : ?>
                <div class="prm-card-image <?php echo esc_attr($aspect_ratio_class); ?>">
                    <a href="<?php echo esc_url($external_url); ?>" target="_blank">
                        <?php echo $thumbnail; ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="prm-card-content">
                <h3 class="prm-card-title">
                    <?php if ($external_url) : ?>
                        <a href="<?php echo esc_url($external_url); ?>" target="_blank">
                            <?php the_title(); ?>
                        </a>
                    <?php else : ?>
                        <?php the_title(); ?>
                    <?php endif; ?>
                </h3>

                <?php if ($settings['show_excerpt'] === 'yes') : ?>
                    <div class="prm-card-excerpt">
                        <?php echo $this->get_custom_excerpt($settings['excerpt_length']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($has_meta) : ?>
                    <div class="prm-card-meta">
                        <?php if ($show_source) : ?>
                            <span class="prm-source">
                                <?php the_field('source_name', $post_id); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($show_date) : ?>
                            <span class="prm-date">
                                <?php echo $this->format_release_date(get_field('release_date', $post_id)); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function format_release_date($date_string) {
        if (empty($date_string)) {
            return 'Date not available';
        }

        // Handle different date formats from ACF
        if (strpos($date_string, '/') !== false) {
            // Handle d/m/Y format (your current ACF return format)
            $date_parts = explode('/', $date_string);
            if (count($date_parts) === 3) {
                // Create DateTime object from d/m/Y format
                $datetime = DateTime::createFromFormat('d/m/Y', $date_string);
                if ($datetime !== false) {
                    return $datetime->format('M j, Y');
                }
            }
        } elseif (strpos($date_string, '-') !== false) {
            // Handle Y-m-d format (recommended ACF return format)
            $timestamp = strtotime($date_string);
            if ($timestamp !== false && $timestamp > 0) {
                return date('M j, Y', $timestamp);
            }
        }

        // Fallback: try generic parsing
        $timestamp = strtotime($date_string);
        if ($timestamp !== false && $timestamp > 0) {
            return date('M j, Y', $timestamp);
        }

        // If all else fails, return the raw date
        return $date_string;
    }

    private function get_custom_excerpt($length = 20) {
        $excerpt = get_the_excerpt();
        $words = explode(' ', $excerpt);

        if (count($words) > $length) {
            $words = array_slice($words, 0, $length);
            $excerpt = implode(' ', $words) . '...';
        }

        return $excerpt;
    }

    private function render_pagination($query, $current_page, $current_sort) {
        $total_pages = $query->max_num_pages;

        if ($total_pages <= 1) {
            return;
        }

        echo '<div class="prm-pagination">';

        $big = 999999999;

        // Preserve sort parameter in pagination links
        $base = add_query_arg('prm_sort', $current_sort, str_replace($big, '%#%', esc_url(get_pagenum_link($big))));

        echo paginate_links(array(
            'base' => $base,
            'format' => '?paged=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'mid_size' => 2,
            'end_size' => 1,
        ));

        echo '</div>';
    }

    private function render_custom_css($settings) {
        $aspect_ratio = '16/9';

        switch ($settings['image_aspect_ratio']) {
            case 'square':
                $aspect_ratio = '1/1';
                break;
            case 'portrait':
                $aspect_ratio = '3/4';
                break;
            case 'landscape':
                $aspect_ratio = '4/3';
                break;
            case 'custom':
                $aspect_ratio = $settings['custom_aspect_ratio'];
                break;
        }

        // Get column settings with fallbacks
        $desktop_columns = $settings['columns'] ?? '3';
        $tablet_columns = $settings['columns_tablet'] ?? '2';
        $mobile_columns = $settings['columns_mobile'] ?? '1';

        // Get grid gap with proper fallbacks
        $grid_gap_size = isset($settings['grid_gap']['size']) ? $settings['grid_gap']['size'] : 20;
        $grid_gap_unit = isset($settings['grid_gap']['unit']) ? $settings['grid_gap']['unit'] : 'px';

        echo '<style>
        .prm-press-releases {
            position: relative;
        }

        .prm-sort-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .prm-sort-label {
            margin-right: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .prm-sort-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            min-width: 200px;
        }

        .prm-sort-select:focus {
            outline: none;
            border-color: #007cba;
        }

        /* Responsive sort controls */
        @media (max-width: 767px) {
            .prm-sort-controls {
                display: block;
            }

            .prm-sort-label {
                display: block;
                margin-bottom: 8px;
                margin-right: 0;
                text-align: center;
            }

            .prm-sort-select {
                width: 100% !important;
                min-width: auto;
            }
        }

        .prm-grid {
            display: grid;
            margin: 20px 0;
            gap: ' . $grid_gap_size . $grid_gap_unit . ';
        }

        /* Desktop Columns - FOR GRID LAYOUT ONLY */
        .prm-layout-grid.prm-grid[data-columns="1"] { grid-template-columns: 1fr; }
        .prm-layout-grid.prm-grid[data-columns="2"] { grid-template-columns: repeat(2, 1fr); }
        .prm-layout-grid.prm-grid[data-columns="3"] { grid-template-columns: repeat(3, 1fr); }
        .prm-layout-grid.prm-grid[data-columns="4"] { grid-template-columns: repeat(4, 1fr); }
        .prm-layout-grid.prm-grid[data-columns="5"] { grid-template-columns: repeat(5, 1fr); }
        .prm-layout-grid.prm-grid[data-columns="6"] { grid-template-columns: repeat(6, 1fr); }

        /* Tablet Columns (1024px and below) - FOR GRID LAYOUT ONLY */
        @media (max-width: 1024px) {
            .prm-layout-grid.prm-grid[data-columns-tablet="1"] { grid-template-columns: 1fr; }
            .prm-layout-grid.prm-grid[data-columns-tablet="2"] { grid-template-columns: repeat(2, 1fr); }
            .prm-layout-grid.prm-grid[data-columns-tablet="3"] { grid-template-columns: repeat(3, 1fr); }
            .prm-layout-grid.prm-grid[data-columns-tablet="4"] { grid-template-columns: repeat(4, 1fr); }
            .prm-layout-grid.prm-grid[data-columns-tablet="5"] { grid-template-columns: repeat(5, 1fr); }
            .prm-layout-grid.prm-grid[data-columns-tablet="6"] { grid-template-columns: repeat(6, 1fr); }
        }

        /* Mobile Columns (767px and below) - FOR GRID LAYOUT ONLY */
        @media (max-width: 767px) {
            .prm-layout-grid.prm-grid[data-columns-mobile="1"] { grid-template-columns: 1fr; }
            .prm-layout-grid.prm-grid[data-columns-mobile="2"] { grid-template-columns: repeat(2, 1fr); }
            .prm-layout-grid.prm-grid[data-columns-mobile="3"] { grid-template-columns: repeat(3, 1fr); }
            .prm-layout-grid.prm-grid[data-columns-mobile="4"] { grid-template-columns: repeat(4, 1fr); }
            .prm-layout-grid.prm-grid[data-columns-mobile="5"] { grid-template-columns: repeat(5, 1fr); }
            .prm-layout-grid.prm-grid[data-columns-mobile="6"] { grid-template-columns: repeat(6, 1fr); }
        }

        /* LIST LAYOUT - FULL WIDTH FIX */
        .prm-layout-list.prm-grid {
            grid-template-columns: 1fr !important; /* Force single column */
            width: 100%;
        }

        .prm-layout-list .prm-card {
            display: flex;
            align-items: stretch;
            width: 100%; /* Ensure full width */
            max-width: 100%; /* Prevent any max-width restrictions */
        }

        .prm-layout-list .prm-card-image {
            flex-shrink: 0;
            overflow: hidden;
        }

        .prm-layout-list .prm-card-image img {
            width: 100%;
            height: 100%;
            display: block;
        }

        .prm-layout-list .prm-card-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0; /* Prevent flex item overflow */
        }

        /* Card Styles */
        .prm-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            width: 100%; /* Ensure full width */
        }

        .prm-layout-grid .prm-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .prm-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        /* Image Aspect Ratios for Grid */
        .prm-layout-grid .prm-card-image {
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .prm-aspect-square { aspect-ratio: 1/1; }
        .prm-aspect-portrait { aspect-ratio: 3/4; }
        .prm-aspect-landscape { aspect-ratio: 4/3; }
        .prm-aspect-custom { aspect-ratio: ' . esc_attr($aspect_ratio) . '; }

        .prm-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Card Content */
        .prm-card-content {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            padding: 20px;
        }

        .prm-card-title {
            margin: 0 0 10px 0;
            line-height: 1.3;
        }
        .prm-card-title a {
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .prm-card-title,
        .prm-card-title a {
            font-family: var(--title-typography-font-family, inherit);
            font-size: var(--title-typography-font-size, 20px);
            font-weight: var(--title-typography-font-weight, 600);
            line-height: var(--title-typography-line-height, 1.3);
            letter-spacing: var(--title-typography-letter-spacing, 0px);
            text-transform: var(--title-typography-text-transform, none);
            font-style: var(--title-typography-font-style, normal);
            text-decoration: var(--title-typography-text-decoration, none);
            color: var(--title-color, #333);
        }


        .prm-card-excerpt {
            color: #666;
            line-height: 1.5;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        /* Meta Styles */
        .prm-card-meta {
            display: flex;
            align-items: center;
            justify-content: ' . (isset($settings['meta_alignment']) ? $settings['meta_alignment'] : 'space-between') . ';
            border-top: 1px solid #f0f0f0;
            padding-top: 15px;
            margin-top: auto;
            width: 100%;
        }

        .prm-card-meta .prm-source,
        .prm-card-meta .prm-date {
            display: inline-block;
            white-space: nowrap;
        }

        .prm-source {
            font-weight: 600;
        }

        /* List Layout Meta Adjustments */
        .prm-layout-list .prm-card-meta {
            border-top: none;
            padding-top: 0;
            margin-top: 10px;
        }

        /* Pagination Styles */
        .prm-pagination {
            margin-top: 40px;
        }

        .prm-pagination .page-numbers {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 2px;
            border: 1px solid #ddd;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .prm-pagination .page-numbers.current {
            font-weight: bold;
        }

        .prm-pagination .page-numbers:hover {
            background-color: #f5f5f5;
        }

        /* Responsive */
        @media (max-width: 767px) {
            .prm-layout-list .prm-card {
                flex-direction: column !important;
            }
            .prm-layout-list .prm-card-image {
                flex: 0 0 auto !important;
                width: 100% !important;
                height: auto !important;
            }
            .prm-card-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .prm-layout-list .prm-card-meta {
                align-items: flex-start;
            }

            .prm-pagination .page-numbers {
                padding: 6px 12px;
                margin: 0 1px;
                font-size: 14px;
            }

        </style>';
    }
}