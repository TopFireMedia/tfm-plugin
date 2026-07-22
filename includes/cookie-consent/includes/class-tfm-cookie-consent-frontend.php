<?php
/**
 * Frontend functionality for TFM Cookie Consent
 *
 * @package TFM_Cookie_Consent
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class
 */
class TFM_Cookie_Consent_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_popup'));
        add_action('wp_head', array($this, 'add_meta_tags'));
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        $settings = get_option('tfm_cookie_consent_settings', array());
        
        if (!isset($settings['enabled']) || !$settings['enabled']) {
            return;
        }
        
        wp_enqueue_style(
            'tfm-cookie-consent-frontend',
            TFM_COOKIE_CONSENT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            TFM_COOKIE_CONSENT_VERSION
        );
        
        wp_enqueue_script(
            'tfm-cookie-consent-frontend',
            TFM_COOKIE_CONSENT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            TFM_COOKIE_CONSENT_VERSION,
            true
        );
        
        wp_localize_script('tfm-cookie-consent-frontend', 'tfm_cookie_consent', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tfm_cookie_consent_nonce'),
            'settings' => $this->get_frontend_settings(),
            'strings' => array(
                'saving' => __('Saving preferences...', 'tfm-cookie-consent'),
                'saved' => __('Preferences saved!', 'tfm-cookie-consent'),
                'error' => __('Error saving preferences.', 'tfm-cookie-consent')
            )
        ));
    }
    
    /**
     * Get frontend settings
     */
    private function get_frontend_settings() {
        $settings = get_option('tfm_cookie_consent_settings', array());
        
        return array(
            'popup_title' => isset($settings['popup_title']) ? $settings['popup_title'] : __('Cookie Consent', 'tfm-cookie-consent'),
            'popup_message' => isset($settings['popup_message']) ? $settings['popup_message'] : __('This website uses cookies to ensure you get the best experience on our website.', 'tfm-cookie-consent'),
            'accept_button_text' => isset($settings['accept_button_text']) ? $settings['accept_button_text'] : __('Accept All', 'tfm-cookie-consent'),
            'deny_button_text' => isset($settings['deny_button_text']) ? $settings['deny_button_text'] : __('Deny All', 'tfm-cookie-consent'),
            'customize_button_text' => isset($settings['customize_button_text']) ? $settings['customize_button_text'] : __('Customize', 'tfm-cookie-consent'),
            'popup_position' => isset($settings['popup_position']) ? $settings['popup_position'] : 'bottom',
            'popup_theme' => isset($settings['popup_theme']) ? $settings['popup_theme'] : 'light',
            'expiry_days' => isset($settings['expiry_days']) ? absint($settings['expiry_days']) : 365,
            'cookie_categories' => isset($settings['cookie_categories']) ? $settings['cookie_categories'] : array(),
            'colors' => array(
                'background_color' => isset($settings['background_color']) ? $settings['background_color'] : '#ffffff',
                'text_color' => isset($settings['text_color']) ? $settings['text_color'] : '#333333',
                'secondary_text_color' => isset($settings['secondary_text_color']) ? $settings['secondary_text_color'] : '#666666',
                'border_color' => isset($settings['border_color']) ? $settings['border_color'] : '#eeeeee',
                'accept_button_color' => isset($settings['accept_button_color']) ? $settings['accept_button_color'] : '#27ae60',
                'accept_button_text_color' => isset($settings['accept_button_text_color']) ? $settings['accept_button_text_color'] : '#ffffff',
                'deny_button_color' => isset($settings['deny_button_color']) ? $settings['deny_button_color'] : '#e74c3c',
                'deny_button_text_color' => isset($settings['deny_button_text_color']) ? $settings['deny_button_text_color'] : '#ffffff',
                'customize_button_color' => isset($settings['customize_button_color']) ? $settings['customize_button_color'] : '#3498db',
                'customize_button_text_color' => isset($settings['customize_button_text_color']) ? $settings['customize_button_text_color'] : '#ffffff',
                'save_button_color' => isset($settings['save_button_color']) ? $settings['save_button_color'] : '#27ae60',
                'save_button_text_color' => isset($settings['save_button_text_color']) ? $settings['save_button_text_color'] : '#ffffff',
                'back_button_color' => isset($settings['back_button_color']) ? $settings['back_button_color'] : '#95a5a6',
                'back_button_text_color' => isset($settings['back_button_text_color']) ? $settings['back_button_text_color'] : '#ffffff',
                'toggle_active_color' => isset($settings['toggle_active_color']) ? $settings['toggle_active_color'] : '#27ae60',
                'toggle_inactive_color' => isset($settings['toggle_inactive_color']) ? $settings['toggle_inactive_color'] : '#cccccc',
                'category_background_color' => isset($settings['category_background_color']) ? $settings['category_background_color'] : '#fafafa',
                'overlay_color' => isset($settings['overlay_color']) ? $settings['overlay_color'] : '#000000',
                'overlay_opacity' => isset($settings['overlay_opacity']) ? absint($settings['overlay_opacity']) : 50
            ),
            'integrations' => array(
                'google_analytics' => isset($settings['google_analytics']) ? $settings['google_analytics'] : false,
                'google_tag_manager' => isset($settings['google_tag_manager']) ? $settings['google_tag_manager'] : false,
                'facebook_pixel' => isset($settings['facebook_pixel']) ? $settings['facebook_pixel'] : false
            ),
            'debug_mode' => isset($settings['debug_mode']) ? $settings['debug_mode'] : false,
            'custom_scripts' => isset($settings['custom_scripts']) ? $settings['custom_scripts'] : ''
        );
    }
    
    /**
     * Add meta tags for privacy compliance
     */
    public function add_meta_tags() {
        $settings = get_option('tfm_cookie_consent_settings', array());
        
        if (!isset($settings['enabled']) || !$settings['enabled']) {
            return;
        }
        
        echo '<meta name="cookie-consent" content="enabled">' . "\n";
    }
    

    
    /**
     * Render the cookie consent popup
     */
    public function render_popup() {
        $settings = get_option('tfm_cookie_consent_settings', array());
        
        if (!isset($settings['enabled']) || !$settings['enabled']) {
            return;
        }
        
        $popup_position = isset($settings['popup_position']) ? $settings['popup_position'] : 'bottom';
        $popup_theme = isset($settings['popup_theme']) ? $settings['popup_theme'] : 'light';
        $categories = isset($settings['cookie_categories']) ? $settings['cookie_categories'] : array();
        
        // Get color settings
        $colors = array(
            'background_color' => isset($settings['background_color']) ? $settings['background_color'] : '#ffffff',
            'text_color' => isset($settings['text_color']) ? $settings['text_color'] : '#333333',
            'secondary_text_color' => isset($settings['secondary_text_color']) ? $settings['secondary_text_color'] : '#666666',
            'border_color' => isset($settings['border_color']) ? $settings['border_color'] : '#eeeeee',
            'accept_button_color' => isset($settings['accept_button_color']) ? $settings['accept_button_color'] : '#27ae60',
            'accept_button_text_color' => isset($settings['accept_button_text_color']) ? $settings['accept_button_text_color'] : '#ffffff',
            'deny_button_color' => isset($settings['deny_button_color']) ? $settings['deny_button_color'] : '#e74c3c',
            'deny_button_text_color' => isset($settings['deny_button_text_color']) ? $settings['deny_button_text_color'] : '#ffffff',
            'customize_button_color' => isset($settings['customize_button_color']) ? $settings['customize_button_color'] : '#3498db',
            'customize_button_text_color' => isset($settings['customize_button_text_color']) ? $settings['customize_button_text_color'] : '#ffffff',
            'save_button_color' => isset($settings['save_button_color']) ? $settings['save_button_color'] : '#27ae60',
            'save_button_text_color' => isset($settings['save_button_text_color']) ? $settings['save_button_text_color'] : '#ffffff',
            'back_button_color' => isset($settings['back_button_color']) ? $settings['back_button_color'] : '#95a5a6',
            'back_button_text_color' => isset($settings['back_button_text_color']) ? $settings['back_button_text_color'] : '#ffffff',
            'toggle_active_color' => isset($settings['toggle_active_color']) ? $settings['toggle_active_color'] : '#27ae60',
            'toggle_inactive_color' => isset($settings['toggle_inactive_color']) ? $settings['toggle_inactive_color'] : '#cccccc',
            'category_background_color' => isset($settings['category_background_color']) ? $settings['category_background_color'] : '#fafafa',
            'overlay_color' => isset($settings['overlay_color']) ? $settings['overlay_color'] : '#000000',
            'overlay_opacity' => isset($settings['overlay_opacity']) ? absint($settings['overlay_opacity']) : 50
        );
        
        ?>
        <div id="tfm-cookie-consent" 
             class="tfm-cookie-consent tfm-cookie-consent--<?php echo esc_attr($popup_position); ?> tfm-cookie-consent--<?php echo esc_attr($popup_theme); ?>" 
             style="display: none;"
             data-colors='<?php echo esc_attr(json_encode($colors)); ?>'>
            <div class="tfm-cookie-consent__overlay"></div>
            <div class="tfm-cookie-consent__container">
                <div class="tfm-cookie-consent__content">
                    <div class="tfm-cookie-consent__header">
                        <h3 class="tfm-cookie-consent__title"><?php echo esc_html($settings['popup_title'] ?? __('Cookie Consent', 'tfm-cookie-consent')); ?></h3>
                        <button type="button" class="tfm-cookie-consent__close" aria-label="<?php esc_attr_e('Close', 'tfm-cookie-consent'); ?>">
                            <span>&times;</span>
                        </button>
                    </div>
                    
                    <div class="tfm-cookie-consent__body">
                        <p class="tfm-cookie-consent__message"><?php echo wp_kses_post($settings['popup_message'] ?? __('This website uses cookies to ensure you get the best experience on our website.', 'tfm-cookie-consent')); ?></p>
                        
                        <!-- Main popup buttons -->
                        <div class="tfm-cookie-consent__buttons tfm-cookie-consent__buttons--main">
                            <button type="button" class="tfm-cookie-consent__button tfm-cookie-consent__button--accept-all">
                                <?php echo esc_html($settings['accept_button_text'] ?? __('Accept All', 'tfm-cookie-consent')); ?>
                            </button>
                            <button type="button" class="tfm-cookie-consent__button tfm-cookie-consent__button--deny-all">
                                <?php echo esc_html($settings['deny_button_text'] ?? __('Deny All', 'tfm-cookie-consent')); ?>
                            </button>
                            <button type="button" class="tfm-cookie-consent__button tfm-cookie-consent__button--customize">
                                <?php echo esc_html($settings['customize_button_text'] ?? __('Customize', 'tfm-cookie-consent')); ?>
                            </button>
                        </div>
                        
                        <!-- Customize view -->
                        <div class="tfm-cookie-consent__customize" style="display: none;">
                            <div class="tfm-cookie-consent__categories">
                                <?php foreach ($categories as $key => $category) : ?>
                                    <div class="tfm-cookie-consent__category" data-category="<?php echo esc_attr($key); ?>">
                                        <div class="tfm-cookie-consent__category-header">
                                            <label class="tfm-cookie-consent__category-toggle">
                                                <input type="checkbox" 
                                                       name="category_<?php echo esc_attr($key); ?>" 
                                                       value="1" 
                                                       <?php checked($category['enabled'] ?? false); ?>
                                                       <?php disabled($category['required'] ?? false); ?>>
                                                <span class="tfm-cookie-consent__toggle-slider"></span>
                                            </label>
                                            <div class="tfm-cookie-consent__category-info">
                                                <h4 class="tfm-cookie-consent__category-name">
                                                    <?php echo esc_html($category['name']); ?>
                                                    <?php if ($category['required'] ?? false) : ?>
                                                        <span class="tfm-cookie-consent__required"><?php _e('(Required)', 'tfm-cookie-consent'); ?></span>
                                                    <?php endif; ?>
                                                </h4>
                                                <p class="tfm-cookie-consent__category-description">
                                                    <?php echo wp_kses_post($category['description']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="tfm-cookie-consent__buttons tfm-cookie-consent__buttons--customize">
                                <button type="button" class="tfm-cookie-consent__button tfm-cookie-consent__button--save-preferences">
                                    <?php _e('Save Preferences', 'tfm-cookie-consent'); ?>
                                </button>
                                <button type="button" class="tfm-cookie-consent__button tfm-cookie-consent__button--back">
                                    <?php _e('Back', 'tfm-cookie-consent'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
} 