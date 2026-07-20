<?php
namespace TFM\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) {
    exit;
}

class Widget_News extends Widget_Base {

    public function get_name() {
        return 'tfm-news';
    }

    public function get_title() {
        return esc_html__('TFM News', 'topfiremedia');
    }

    public function get_icon() {
        return 'eicon-posts-grid';
    }

    public function get_categories() {
        return ['tfm'];
    }

    public function get_keywords() {
        return ['news', 'posts', 'articles', 'outbound', 'tfm'];
    }

    protected function register_controls() {
        $news_enabled = function_exists('tfm_news_is_enabled') && tfm_news_is_enabled();

        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Content', 'topfiremedia'),
            ]
        );

        $this->add_control(
            'news_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf(
                    '<div style="background:#1f2933;border:1px solid #323f4b;border-radius:4px;padding:10px 14px;font-size:12px;line-height:1.6;color:#f8fafc;"><span style="color:%s;">●</span> %s<br><span style="font-size:11px;color:#cbd5e1;display:block;margin-top:4px;">%s</span></div>',
                    esc_attr($news_enabled ? '#059669' : '#d97706'),
                    esc_html($news_enabled ? __('News feature is enabled', 'topfiremedia') : __('News feature is disabled', 'topfiremedia')),
                    esc_html__('Configure in TFM Custom Functions → News Settings', 'topfiremedia')
                ),
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label' => esc_html__('Columns', 'topfiremedia'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => [
                    '1' => esc_html__('1', 'topfiremedia'),
                    '2' => esc_html__('2', 'topfiremedia'),
                    '3' => esc_html__('3', 'topfiremedia'),
                    '4' => esc_html__('4', 'topfiremedia'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
                ],
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => esc_html__('Posts Per Page', 'topfiremedia'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'min' => 1,
                'max' => 50,
            ]
        );

        $this->add_control(
            'show_image',
            [
                'label' => esc_html__('Show Featured Image', 'topfiremedia'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'topfiremedia'),
                'label_off' => esc_html__('No', 'topfiremedia'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'image_size',
            [
                'label' => esc_html__('Image Size', 'topfiremedia'),
                'type' => Controls_Manager::SELECT,
                'default' => 'large',
                'options' => [
                    'thumbnail' => esc_html__('Thumbnail', 'topfiremedia'),
                    'medium' => esc_html__('Medium', 'topfiremedia'),
                    'medium_large' => esc_html__('Medium Large', 'topfiremedia'),
                    'large' => esc_html__('Large', 'topfiremedia'),
                    'full' => esc_html__('Full', 'topfiremedia'),
                ],
                'condition' => [
                    'show_image' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_source',
            [
                'label' => esc_html__('Show Source Name', 'topfiremedia'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'topfiremedia'),
                'label_off' => esc_html__('No', 'topfiremedia'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_date',
            [
                'label' => esc_html__('Show Date', 'topfiremedia'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'topfiremedia'),
                'label_off' => esc_html__('No', 'topfiremedia'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_excerpt',
            [
                'label' => esc_html__('Show Excerpt', 'topfiremedia'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'topfiremedia'),
                'label_off' => esc_html__('No', 'topfiremedia'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'excerpt_length',
            [
                'label' => esc_html__('Excerpt Length (words)', 'topfiremedia'),
                'type' => Controls_Manager::NUMBER,
                'default' => 22,
                'min' => 5,
                'max' => 80,
                'condition' => [
                    'show_excerpt' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_button',
            [
                'label' => esc_html__('Show Button Text', 'topfiremedia'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'topfiremedia'),
                'label_off' => esc_html__('No', 'topfiremedia'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => esc_html__('Button Text', 'topfiremedia'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Read Article', 'topfiremedia'),
                'condition' => [
                    'show_button' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'pagination',
            [
                'label' => esc_html__('Pagination', 'topfiremedia'),
                'type' => Controls_Manager::SELECT,
                'default' => 'numbers',
                'options' => [
                    'none' => esc_html__('None', 'topfiremedia'),
                    'numbers' => esc_html__('Numbers', 'topfiremedia'),
                    'prev_next' => esc_html__('Previous / Next', 'topfiremedia'),
                    'numbers_and_prev_next' => esc_html__('Numbers + Previous / Next', 'topfiremedia'),
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_query',
            [
                'label' => esc_html__('Query', 'topfiremedia'),
            ]
        );

        $this->add_control(
            'offset',
            [
                'label' => esc_html__('Offset', 'topfiremedia'),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => esc_html__('Order By', 'topfiremedia'),
                'type' => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => esc_html__('Date', 'topfiremedia'),
                    'title' => esc_html__('Title', 'topfiremedia'),
                    'menu_order' => esc_html__('Menu Order', 'topfiremedia'),
                    'rand' => esc_html__('Random', 'topfiremedia'),
                ],
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => esc_html__('Order', 'topfiremedia'),
                'type' => Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'DESC' => esc_html__('Descending', 'topfiremedia'),
                    'ASC' => esc_html__('Ascending', 'topfiremedia'),
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_layout',
            [
                'label' => esc_html__('Cards', 'topfiremedia'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'card_gap',
            [
                'label' => esc_html__('Gap', 'topfiremedia'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 80,
                    ],
                ],
                'default' => [
                    'size' => 24,
                ],
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'card_bg_color',
            [
                'label' => esc_html__('Card Background', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-card-link' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'card_padding',
            [
                'label' => esc_html__('Card Padding', 'topfiremedia'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .tfm-news-card-link',
            ]
        );

        $this->add_responsive_control(
            'card_border_radius',
            [
                'label' => esc_html__('Border Radius', 'topfiremedia'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-card-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_shadow',
                'selector' => '{{WRAPPER}} .tfm-news-card-link',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_image',
            [
                'label' => esc_html__('Image', 'topfiremedia'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_image' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'image_ratio',
            [
                'label' => esc_html__('Aspect Ratio', 'topfiremedia'),
                'type' => Controls_Manager::SELECT,
                'default' => '56.25',
                'options' => [
                    '56.25' => esc_html__('16:9', 'topfiremedia'),
                    '75' => esc_html__('4:3', 'topfiremedia'),
                    '100' => esc_html__('1:1', 'topfiremedia'),
                    '66.666' => esc_html__('3:2', 'topfiremedia'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-image-wrap' => '--tfm-news-image-ratio: {{VALUE}}%;',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_text',
            [
                'label' => esc_html__('Text', 'topfiremedia'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'source_color',
            [
                'label' => esc_html__('Source/Date Color', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-meta, {{WRAPPER}} .tfm-news-source, {{WRAPPER}} .tfm-news-date' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'source_typography',
                'selector' => '{{WRAPPER}} .tfm-news-meta',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => esc_html__('Title Color', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .tfm-news-title',
            ]
        );

        $this->add_control(
            'excerpt_color',
            [
                'label' => esc_html__('Excerpt Color', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-excerpt' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_excerpt' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'excerpt_typography',
                'selector' => '{{WRAPPER}} .tfm-news-excerpt',
                'condition' => [
                    'show_excerpt' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => esc_html__('Button Text Color', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-read-more' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_button' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .tfm-news-read-more',
                'condition' => [
                    'show_button' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_pagination',
            [
                'label' => esc_html__('Pagination', 'topfiremedia'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'pagination!' => 'none',
                ],
            ]
        );

        $this->add_control(
            'pagination_color',
            [
                'label' => esc_html__('Text Color', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-pagination a, {{WRAPPER}} .tfm-news-pagination span' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_bg',
            [
                'label' => esc_html__('Background', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-pagination a, {{WRAPPER}} .tfm-news-pagination span' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'pagination_active_bg',
            [
                'label' => esc_html__('Active Background', 'topfiremedia'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .tfm-news-pagination .current' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!function_exists('tfm_load_settings')) {
            return;
        }

        $plugin_settings = tfm_load_settings();
        $is_enabled = !empty($plugin_settings['enable_news']);

        if (!$is_enabled) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div style="padding:16px;border:1px dashed #ccc;background:#fafafa;color:#666;">'
                    . esc_html__('TFM News is disabled. Enable it in TFM Custom Functions → News Settings.', 'topfiremedia')
                    . '</div>';
            }
            return;
        }

        $settings = $this->get_settings_for_display();

        $posts_per_page = max(1, absint($settings['posts_per_page'] ?? 6));
        $offset = max(0, absint($settings['offset'] ?? 0));
        $orderby = in_array($settings['orderby'] ?? 'date', ['date', 'title', 'menu_order', 'rand'], true) ? $settings['orderby'] : 'date';
        $order = strtoupper($settings['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $pagination_mode = $settings['pagination'] ?? 'numbers';

        $page_var = 'tfm_news_page_' . $this->get_id();
        $current_page = isset($_GET[$page_var]) ? max(1, absint(wp_unslash($_GET[$page_var]))) : 1;

        $query_args = [
            'post_type' => 'tfm_news',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'orderby' => $orderby,
            'order' => $order,
            'ignore_sticky_posts' => true,
            'offset' => $offset + (($current_page - 1) * $posts_per_page),
            'no_found_rows' => ($pagination_mode === 'none'),
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_tfm_news_outbound_url',
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => '_tfm_news_outbound_url',
                    'value' => '',
                    'compare' => '!=',
                ],
            ],
        ];

        $query = new \WP_Query($query_args);

        if (!$query->have_posts()) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div style="padding:16px;border:1px dashed #ccc;background:#fafafa;color:#666;">'
                    . esc_html__('No news items found. Add News Items in TFM Custom Functions → News Settings.', 'topfiremedia')
                    . '</div>';
            }
            return;
        }

        $show_image = ($settings['show_image'] ?? 'yes') === 'yes';
        $show_source = ($settings['show_source'] ?? 'yes') === 'yes';
        $show_date = ($settings['show_date'] ?? 'yes') === 'yes';
        $show_excerpt = ($settings['show_excerpt'] ?? 'yes') === 'yes';
        $show_button = ($settings['show_button'] ?? 'yes') === 'yes';
        $image_size = $settings['image_size'] ?? 'large';
        $excerpt_length = max(5, absint($settings['excerpt_length'] ?? 22));
        $button_text = !empty($settings['button_text']) ? $settings['button_text'] : __('Read Article', 'topfiremedia');

        echo '<div class="tfm-news-widget">';
        echo '<div class="tfm-news-grid">';

        while ($query->have_posts()) {
            $query->the_post();

            $post_id = get_the_ID();
            $outbound_url = get_post_meta($post_id, '_tfm_news_outbound_url', true);
            $source_name = get_post_meta($post_id, '_tfm_news_source_name', true);

            if (empty($outbound_url)) {
                continue;
            }

            echo '<article class="tfm-news-card">';
            printf(
                '<a class="tfm-news-card-link" href="%s" target="_blank" rel="noopener">',
                esc_url($outbound_url)
            );

            if ($show_image && has_post_thumbnail($post_id)) {
                echo '<div class="tfm-news-image-wrap">';
                echo get_the_post_thumbnail($post_id, $image_size, ['class' => 'tfm-news-image']);
                echo '</div>';
            }

            echo '<div class="tfm-news-content">';

            if ($show_source || $show_date) {
                echo '<div class="tfm-news-meta">';
                if ($show_source && !empty($source_name)) {
                    echo '<span class="tfm-news-source">' . esc_html($source_name) . '</span>';
                }
                if ($show_date) {
                    $separator = ($show_source && !empty($source_name)) ? '<span class="tfm-news-separator"> · </span>' : '';
                    echo $separator . '<span class="tfm-news-date">' . esc_html(get_the_date()) . '</span>';
                }
                echo '</div>';
            }

            echo '<h3 class="tfm-news-title">' . esc_html(get_the_title()) . '</h3>';

            if ($show_excerpt) {
                $excerpt = get_the_excerpt();
                if (empty($excerpt)) {
                    $excerpt = wp_strip_all_tags(get_the_content(null, false, $post_id));
                }
                if (!empty($excerpt)) {
                    echo '<div class="tfm-news-excerpt">' . esc_html(wp_trim_words($excerpt, $excerpt_length)) . '</div>';
                }
            }

            if ($show_button) {
                echo '<span class="tfm-news-read-more">' . esc_html($button_text) . '</span>';
            }

            echo '</div>';
            echo '</a>';
            echo '</article>';
        }

        echo '</div>';

        if ($pagination_mode !== 'none') {
            $total_posts = max(0, (int) $query->found_posts - $offset);
            $total_pages = (int) ceil($total_posts / $posts_per_page);

            if ($total_pages > 1) {
                $base_url = remove_query_arg($page_var);
                echo '<nav class="tfm-news-pagination" aria-label="' . esc_attr__('News Pagination', 'topfiremedia') . '">';

                if ($pagination_mode === 'prev_next') {
                    if ($current_page > 1) {
                        echo '<a class="prev page-numbers" href="' . esc_url(add_query_arg($page_var, $current_page - 1, $base_url)) . '">' . esc_html__('« Previous', 'topfiremedia') . '</a>';
                    }
                    if ($current_page < $total_pages) {
                        echo '<a class="next page-numbers" href="' . esc_url(add_query_arg($page_var, $current_page + 1, $base_url)) . '">' . esc_html__('Next »', 'topfiremedia') . '</a>';
                    }
                } else {
                    $links = paginate_links([
                        'base' => add_query_arg($page_var, '%#%', $base_url),
                        'format' => '',
                        'current' => $current_page,
                        'total' => $total_pages,
                        'type' => 'array',
                        'prev_next' => ($pagination_mode === 'numbers_and_prev_next'),
                        'prev_text' => esc_html__('« Previous', 'topfiremedia'),
                        'next_text' => esc_html__('Next »', 'topfiremedia'),
                    ]);

                    if (!empty($links)) {
                        foreach ($links as $link) {
                            echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                    }
                }

                echo '</nav>';
            }
        }

        echo '</div>';

        wp_reset_postdata();
    }
}
