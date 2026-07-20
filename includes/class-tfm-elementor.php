<?php
/**
 * TFM Elementor Integration Class
 * Handles Elementor widget registration and category creation
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TFM_Elementor {
    
    /**
     * Plugin instance
     *
     * @var TFM_Elementor
     */
    private static $instance = null;
    
    /**
     * Get instance
     *
     * @return TFM_Elementor
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook into Elementor's initialization
        add_action('elementor/elements/categories_registered', array($this, 'register_widget_category'));
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'enqueue_widget_styles'));
        add_action('elementor/editor/after_enqueue_styles', array($this, 'enqueue_widget_styles'));
    }
    
    /**
     * Register TFM widget category
     */
    public function register_widget_category($elements_manager) {
        $elements_manager->add_category(
            'tfm',
            array(
                'title' => __('TFM', 'topfiremedia'),
                'icon' => 'fa fa-plug',
            )
        );
    }
    
    /**
     * Register widgets
     */
    public function register_widgets($widgets_manager) {
        // Include widget files
        require_once TFM_PLUGIN_DIR . 'includes/elementor-widgets/widget-phone.php';
        require_once TFM_PLUGIN_DIR . 'includes/elementor-widgets/widget-email.php';
        require_once TFM_PLUGIN_DIR . 'includes/elementor-widgets/widget-lead-magnet.php';
        require_once TFM_PLUGIN_DIR . 'includes/elementor-widgets/widget-news.php';

        // Register widgets
        $widgets_manager->register(new \TFM\Elementor\Widget_Phone());
        $widgets_manager->register(new \TFM\Elementor\Widget_Email());
        $widgets_manager->register(new \TFM\Elementor\Widget_Lead_Magnet());
        $widgets_manager->register(new \TFM\Elementor\Widget_News());
    }
    
    /**
     * Enqueue widget styles
     */
    public function enqueue_widget_styles() {
        wp_enqueue_style(
            'tfm-elementor-contact-widgets',
            TFM_PLUGIN_URL . 'assets/css/elementor-contact-widgets.css',
            [],
            TFM_PLUGIN_VERSION
        );
    }
    
    /**
     * Check if Elementor is active
     *
     * @return bool
     */
    public static function is_elementor_active() {
        return did_action('elementor/loaded');
    }
}

