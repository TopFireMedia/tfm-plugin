<?php
/**
 * Admin functionality for TFM Cookie Consent
 *
 * @package TFM_Cookie_Consent
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class TFM_Cookie_Consent_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_tfm_save_cookie_settings', array($this, 'save_cookie_settings'));
        add_action('wp_ajax_tfm_clear_blocked_cookies', array($this, 'clear_blocked_cookies'));
        add_action('wp_ajax_tfm_export_analytics', array($this, 'export_analytics'));
        add_action('wp_ajax_tfm_clear_discovered_cookies', array($this, 'clear_discovered_cookies'));
        add_action('wp_ajax_tfm_export_cookie_list', array($this, 'export_cookie_list'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('TFM Cookie Consent', 'tfm-cookie-consent'),
            __('Cookie Consent', 'tfm-cookie-consent'),
            'manage_options',
            'tfm-cookie-consent',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            'tfm_cookie_consent_settings',
            'tfm_cookie_consent_settings',
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_tfm-cookie-consent' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'tfm-cookie-consent-admin',
            TFM_COOKIE_CONSENT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TFM_COOKIE_CONSENT_VERSION
        );
        
        wp_enqueue_script(
            'tfm-cookie-consent-admin',
            TFM_COOKIE_CONSENT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            TFM_COOKIE_CONSENT_VERSION,
            true
        );
        
        wp_localize_script('tfm-cookie-consent-admin', 'tfm_cookie_consent_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tfm_cookie_consent_nonce'),
            'strings' => array(
                'saved' => __('Settings saved successfully!', 'tfm-cookie-consent'),
                'error' => __('Error saving settings.', 'tfm-cookie-consent')
            )
        ));
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        $settings = get_option('tfm_cookie_consent_settings', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php" id="tfm-cookie-consent-form">
                <?php settings_fields('tfm_cookie_consent_settings'); ?>
                
                <div class="tfm-cookie-consent-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'tfm-cookie-consent'); ?></a>
                        <a href="#popup" class="nav-tab"><?php _e('Popup Settings', 'tfm-cookie-consent'); ?></a>
                        <a href="#colors" class="nav-tab"><?php _e('Colors', 'tfm-cookie-consent'); ?></a>
                        <a href="#categories" class="nav-tab"><?php _e('Cookie Categories', 'tfm-cookie-consent'); ?></a>
                        <a href="#integrations" class="nav-tab"><?php _e('Integrations', 'tfm-cookie-consent'); ?></a>
                        <a href="#analytics" class="nav-tab"><?php _e('Analytics', 'tfm-cookie-consent'); ?></a>
                        <a href="#blocked" class="nav-tab"><?php _e('Cookie Discovery', 'tfm-cookie-consent'); ?></a>
                        <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'tfm-cookie-consent'); ?></a>
                    </nav>
                    
                    <!-- General Tab -->
                    <div id="general" class="tab-content active">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="enabled"><?php _e('Enable Cookie Consent', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="enabled" name="tfm_cookie_consent_settings[enabled]" value="1" <?php checked(isset($settings['enabled']) ? $settings['enabled'] : true); ?>>
                                    <p class="description"><?php _e('Enable or disable the cookie consent popup.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="expiry_days"><?php _e('Consent Expiry (Days)', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="expiry_days" name="tfm_cookie_consent_settings[expiry_days]" value="<?php echo esc_attr(isset($settings['expiry_days']) ? $settings['expiry_days'] : 365); ?>" min="1" max="3650">
                                    <p class="description"><?php _e('How long to remember the user\'s consent choice (1-3650 days).', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Popup Settings Tab -->
                    <div id="popup" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="popup_title"><?php _e('Popup Title', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="popup_title" name="tfm_cookie_consent_settings[popup_title]" value="<?php echo esc_attr(isset($settings['popup_title']) ? $settings['popup_title'] : __('Cookie Consent', 'tfm-cookie-consent')); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="popup_message"><?php _e('Popup Message', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <textarea id="popup_message" name="tfm_cookie_consent_settings[popup_message]" rows="4" class="large-text"><?php echo esc_textarea(isset($settings['popup_message']) ? $settings['popup_message'] : __('This website uses cookies to ensure you get the best experience on our website.', 'tfm-cookie-consent')); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="accept_button_text"><?php _e('Accept All Button Text', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="accept_button_text" name="tfm_cookie_consent_settings[accept_button_text]" value="<?php echo esc_attr(isset($settings['accept_button_text']) ? $settings['accept_button_text'] : __('Accept All', 'tfm-cookie-consent')); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="deny_button_text"><?php _e('Deny All Button Text', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="deny_button_text" name="tfm_cookie_consent_settings[deny_button_text]" value="<?php echo esc_attr(isset($settings['deny_button_text']) ? $settings['deny_button_text'] : __('Deny All', 'tfm-cookie-consent')); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="customize_button_text"><?php _e('Customize Button Text', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="customize_button_text" name="tfm_cookie_consent_settings[customize_button_text]" value="<?php echo esc_attr(isset($settings['customize_button_text']) ? $settings['customize_button_text'] : __('Customize', 'tfm-cookie-consent')); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="popup_position"><?php _e('Popup Position', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <select id="popup_position" name="tfm_cookie_consent_settings[popup_position]">
                                        <option value="bottom" <?php selected(isset($settings['popup_position']) ? $settings['popup_position'] : 'bottom', 'bottom'); ?>><?php _e('Bottom', 'tfm-cookie-consent'); ?></option>
                                        <option value="top" <?php selected(isset($settings['popup_position']) ? $settings['popup_position'] : 'bottom', 'top'); ?>><?php _e('Top', 'tfm-cookie-consent'); ?></option>
                                        <option value="center" <?php selected(isset($settings['popup_position']) ? $settings['popup_position'] : 'bottom', 'center'); ?>><?php _e('Center', 'tfm-cookie-consent'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="popup_theme"><?php _e('Popup Theme', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <select id="popup_theme" name="tfm_cookie_consent_settings[popup_theme]">
                                        <option value="light" <?php selected(isset($settings['popup_theme']) ? $settings['popup_theme'] : 'light', 'light'); ?>><?php _e('Light', 'tfm-cookie-consent'); ?></option>
                                        <option value="dark" <?php selected(isset($settings['popup_theme']) ? $settings['popup_theme'] : 'light', 'dark'); ?>><?php _e('Dark', 'tfm-cookie-consent'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Colors Tab -->
                    <div id="colors" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="background_color"><?php _e('Background Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="background_color" name="tfm_cookie_consent_settings[background_color]" value="<?php echo esc_attr(isset($settings['background_color']) ? $settings['background_color'] : '#ffffff'); ?>">
                                    <p class="description"><?php _e('Main popup background color.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="text_color"><?php _e('Text Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="text_color" name="tfm_cookie_consent_settings[text_color]" value="<?php echo esc_attr(isset($settings['text_color']) ? $settings['text_color'] : '#333333'); ?>">
                                    <p class="description"><?php _e('Main text color for titles and content.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="secondary_text_color"><?php _e('Secondary Text Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="secondary_text_color" name="tfm_cookie_consent_settings[secondary_text_color]" value="<?php echo esc_attr(isset($settings['secondary_text_color']) ? $settings['secondary_text_color'] : '#666666'); ?>">
                                    <p class="description"><?php _e('Color for descriptions and secondary text.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="border_color"><?php _e('Border Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="border_color" name="tfm_cookie_consent_settings[border_color]" value="<?php echo esc_attr(isset($settings['border_color']) ? $settings['border_color'] : '#eeeeee'); ?>">
                                    <p class="description"><?php _e('Color for borders and dividers.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="accept_button_color"><?php _e('Accept Button Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="accept_button_color" name="tfm_cookie_consent_settings[accept_button_color]" value="<?php echo esc_attr(isset($settings['accept_button_color']) ? $settings['accept_button_color'] : '#27ae60'); ?>">
                                    <p class="description"><?php _e('Background color for the Accept All button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="accept_button_text_color"><?php _e('Accept Button Text Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="accept_button_text_color" name="tfm_cookie_consent_settings[accept_button_text_color]" value="<?php echo esc_attr(isset($settings['accept_button_text_color']) ? $settings['accept_button_text_color'] : '#ffffff'); ?>">
                                    <p class="description"><?php _e('Text color for the Accept All button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="deny_button_color"><?php _e('Deny Button Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="deny_button_color" name="tfm_cookie_consent_settings[deny_button_color]" value="<?php echo esc_attr(isset($settings['deny_button_color']) ? $settings['deny_button_color'] : '#e74c3c'); ?>">
                                    <p class="description"><?php _e('Background color for the Deny All button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="deny_button_text_color"><?php _e('Deny Button Text Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="deny_button_text_color" name="tfm_cookie_consent_settings[deny_button_text_color]" value="<?php echo esc_attr(isset($settings['deny_button_text_color']) ? $settings['deny_button_text_color'] : '#ffffff'); ?>">
                                    <p class="description"><?php _e('Text color for the Deny All button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="customize_button_color"><?php _e('Customize Button Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="customize_button_color" name="tfm_cookie_consent_settings[customize_button_color]" value="<?php echo esc_attr(isset($settings['customize_button_color']) ? $settings['customize_button_color'] : '#3498db'); ?>">
                                    <p class="description"><?php _e('Background color for the Customize button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="customize_button_text_color"><?php _e('Customize Button Text Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="customize_button_text_color" name="tfm_cookie_consent_settings[customize_button_text_color]" value="<?php echo esc_attr(isset($settings['customize_button_text_color']) ? $settings['customize_button_text_color'] : '#ffffff'); ?>">
                                    <p class="description"><?php _e('Text color for the Customize button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="save_button_color"><?php _e('Save Button Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="save_button_color" name="tfm_cookie_consent_settings[save_button_color]" value="<?php echo esc_attr(isset($settings['save_button_color']) ? $settings['save_button_color'] : '#27ae60'); ?>">
                                    <p class="description"><?php _e('Background color for the Save Preferences button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="save_button_text_color"><?php _e('Save Button Text Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="save_button_text_color" name="tfm_cookie_consent_settings[save_button_text_color]" value="<?php echo esc_attr(isset($settings['save_button_text_color']) ? $settings['save_button_text_color'] : '#ffffff'); ?>">
                                    <p class="description"><?php _e('Text color for the Save Preferences button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="back_button_color"><?php _e('Back Button Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="back_button_color" name="tfm_cookie_consent_settings[back_button_color]" value="<?php echo esc_attr(isset($settings['back_button_color']) ? $settings['back_button_color'] : '#95a5a6'); ?>">
                                    <p class="description"><?php _e('Background color for the Back button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="back_button_text_color"><?php _e('Back Button Text Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="back_button_text_color" name="tfm_cookie_consent_settings[back_button_text_color]" value="<?php echo esc_attr(isset($settings['back_button_text_color']) ? $settings['back_button_text_color'] : '#ffffff'); ?>">
                                    <p class="description"><?php _e('Text color for the Back button.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="toggle_active_color"><?php _e('Toggle Active Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="toggle_active_color" name="tfm_cookie_consent_settings[toggle_active_color]" value="<?php echo esc_attr(isset($settings['toggle_active_color']) ? $settings['toggle_active_color'] : '#27ae60'); ?>">
                                    <p class="description"><?php _e('Color for active toggle switches in customize view.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="toggle_inactive_color"><?php _e('Toggle Inactive Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="toggle_inactive_color" name="tfm_cookie_consent_settings[toggle_inactive_color]" value="<?php echo esc_attr(isset($settings['toggle_inactive_color']) ? $settings['toggle_inactive_color'] : '#cccccc'); ?>">
                                    <p class="description"><?php _e('Color for inactive toggle switches in customize view.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="category_background_color"><?php _e('Category Background Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="category_background_color" name="tfm_cookie_consent_settings[category_background_color]" value="<?php echo esc_attr(isset($settings['category_background_color']) ? $settings['category_background_color'] : '#fafafa'); ?>">
                                    <p class="description"><?php _e('Background color for category items in customize view.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="overlay_color"><?php _e('Overlay Color', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="color" id="overlay_color" name="tfm_cookie_consent_settings[overlay_color]" value="<?php echo esc_attr(isset($settings['overlay_color']) ? $settings['overlay_color'] : '#000000'); ?>">
                                    <p class="description"><?php _e('Background overlay color (use alpha for transparency).', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="overlay_opacity"><?php _e('Overlay Opacity', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="range" id="overlay_opacity" name="tfm_cookie_consent_settings[overlay_opacity]" min="0" max="100" value="<?php echo esc_attr(isset($settings['overlay_opacity']) ? $settings['overlay_opacity'] : 50); ?>">
                                    <span id="overlay_opacity_value"><?php echo esc_html(isset($settings['overlay_opacity']) ? $settings['overlay_opacity'] : 50); ?>%</span>
                                    <p class="description"><?php _e('Opacity of the background overlay (0-100%).', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Cookie Categories Tab -->
                    <div id="categories" class="tab-content">
                        <p><?php _e('Configure cookie categories and their descriptions.', 'tfm-cookie-consent'); ?></p>
                        <div id="cookie-categories">
                            <?php
                            $categories = isset($settings['cookie_categories']) ? $settings['cookie_categories'] : array();
                            foreach ($categories as $key => $category) {
                                $this->render_category_fields($key, $category);
                            }
                            ?>
                        </div>
                        <button type="button" id="add-category" class="button"><?php _e('Add Category', 'tfm-cookie-consent'); ?></button>
                    </div>
                    
                    <!-- Integrations Tab -->
                    <div id="integrations" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="google_analytics"><?php _e('Google Analytics', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="google_analytics" name="tfm_cookie_consent_settings[google_analytics]" value="1" <?php checked(isset($settings['google_analytics']) ? $settings['google_analytics'] : false); ?>>
                                    <p class="description"><?php _e('Enable Google Analytics integration (requires analytics category consent).', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="google_tag_manager"><?php _e('Google Tag Manager', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="google_tag_manager" name="tfm_cookie_consent_settings[google_tag_manager]" value="1" <?php checked(isset($settings['google_tag_manager']) ? $settings['google_tag_manager'] : false); ?>>
                                    <p class="description"><?php _e('Enable Google Tag Manager integration.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="facebook_pixel"><?php _e('Facebook Pixel', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="facebook_pixel" name="tfm_cookie_consent_settings[facebook_pixel]" value="1" <?php checked(isset($settings['facebook_pixel']) ? $settings['facebook_pixel'] : false); ?>>
                                    <p class="description"><?php _e('Enable Facebook Pixel integration (requires marketing category consent).', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Analytics Tab -->
                    <div id="analytics" class="tab-content">
                        <h3><?php _e('Consent Analytics', 'tfm-cookie-consent'); ?></h3>
                        
                        <?php
                        $consent_stats = TFM_Cookie_Consent_Settings::get_statistics();
                        $cookie_stats = TFM_Cookie_Consent_Cookies::get_cookie_statistics();
                        ?>
                        
                        <!-- Consent Statistics -->
                        <div class="analytics-section">
                            <h4><?php _e('Consent Statistics', 'tfm-cookie-consent'); ?></h4>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Metric', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Value', 'tfm-cookie-consent'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php _e('Total Consents', 'tfm-cookie-consent'); ?></td>
                                        <td><?php echo esc_html($consent_stats['total_consents']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Accept All', 'tfm-cookie-consent'); ?></td>
                                        <td><?php echo esc_html($consent_stats['accept_all']); ?> (<?php echo $consent_stats['total_consents'] > 0 ? round(($consent_stats['accept_all'] / $consent_stats['total_consents']) * 100, 1) : 0; ?>%)</td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Deny All', 'tfm-cookie-consent'); ?></td>
                                        <td><?php echo esc_html($consent_stats['deny_all']); ?> (<?php echo $consent_stats['total_consents'] > 0 ? round(($consent_stats['deny_all'] / $consent_stats['total_consents']) * 100, 1) : 0; ?>%)</td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Customize', 'tfm-cookie-consent'); ?></td>
                                        <td><?php echo esc_html($consent_stats['customize']); ?> (<?php echo $consent_stats['total_consents'] > 0 ? round(($consent_stats['customize'] / $consent_stats['total_consents']) * 100, 1) : 0; ?>%)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Category Acceptance Rates -->
                        <?php if (!empty($consent_stats['categories'])) : ?>
                        <div class="analytics-section">
                            <h4><?php _e('Category Acceptance Rates', 'tfm-cookie-consent'); ?></h4>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Category', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Accepted', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Denied', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Acceptance Rate', 'tfm-cookie-consent'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($consent_stats['categories'] as $category => $stats) : ?>
                                        <tr>
                                            <td><?php echo esc_html(ucfirst($category)); ?></td>
                                            <td><?php echo esc_html($stats['accepted']); ?></td>
                                            <td><?php echo esc_html($stats['denied']); ?></td>
                                            <td><?php echo esc_html(round(($stats['accepted'] / ($stats['accepted'] + $stats['denied'])) * 100, 1)); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Cookie Blocking Statistics -->
                        <div class="analytics-section">
                            <h4><?php _e('Cookie Blocking Statistics', 'tfm-cookie-consent'); ?></h4>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Metric', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Value', 'tfm-cookie-consent'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php _e('Total Blocked Cookies', 'tfm-cookie-consent'); ?></td>
                                        <td><?php echo esc_html($cookie_stats['total_blocked']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Blocked by JavaScript', 'tfm-cookie-consent'); ?></td>
                                        <td><?php echo esc_html($cookie_stats['by_source']['javascript']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Blocked by PHP', 'tfm-cookie-consent'); ?></td>
                                        <td><?php echo esc_html($cookie_stats['by_source']['php']); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Cookie Categories Blocked -->
                        <?php if ($cookie_stats['total_blocked'] > 0) : ?>
                        <div class="analytics-section">
                            <h4><?php _e('Cookies Blocked by Category', 'tfm-cookie-consent'); ?></h4>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Category', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Count', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Percentage', 'tfm-cookie-consent'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cookie_stats['by_category'] as $category => $count) : ?>
                                        <?php if ($count > 0) : ?>
                                            <tr>
                                                <td><?php echo esc_html(ucfirst($category)); ?></td>
                                                <td><?php echo esc_html($count); ?></td>
                                                <td><?php echo esc_html(round(($count / $cookie_stats['total_blocked']) * 100, 1)); ?>%</td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Export Data -->
                        <div class="analytics-section">
                            <h4><?php _e('Export Data', 'tfm-cookie-consent'); ?></h4>
                            <p><?php _e('Export consent and cookie data for external analysis.', 'tfm-cookie-consent'); ?></p>
                            <button type="button" id="export-analytics" class="button button-secondary">
                                <?php _e('Export Analytics Data', 'tfm-cookie-consent'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Blocked Cookies Tab -->
                    <div id="blocked" class="tab-content">
                        <h3><?php _e('Cookie Discovery & Tracking', 'tfm-cookie-consent'); ?></h3>
                        <p><?php _e('This page shows all cookies discovered on your website, categorized by type. This information is essential for creating accurate privacy policies.', 'tfm-cookie-consent'); ?></p>
                        
                        <?php
                        // Discover cookies on page load
                        $discovered_cookies = TFM_Cookie_Consent_Cookies::discover_cookies();
                        $discovery_stats = TFM_Cookie_Consent_Cookies::get_cookie_discovery_statistics();
                        $blocked_cookies = get_option('tfm_cookie_consent_blocked_cookies', array());
                        $cookie_stats = TFM_Cookie_Consent_Cookies::get_cookie_statistics();
                        
                        if (!empty($discovered_cookies)) : ?>
                            <!-- Cookie Discovery Statistics -->
                            <div class="cookie-stats">
                                <h4><?php _e('Cookie Discovery Statistics', 'tfm-cookie-consent'); ?></h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Category', 'tfm-cookie-consent'); ?></th>
                                            <th><?php _e('Count', 'tfm-cookie-consent'); ?></th>
                                            <th><?php _e('Percentage', 'tfm-cookie-consent'); ?></th>
                                            <th><?php _e('Description', 'tfm-cookie-consent'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $categories = array(
                                            'necessary' => 'Essential for website functionality',
                                            'analytics' => 'Website usage and performance tracking',
                                            'marketing' => 'Advertising and marketing tracking',
                                            'functional' => 'Enhanced features and preferences',
                                            'unknown' => 'Uncategorized cookies'
                                        );
                                        ?>
                                        <?php foreach ($categories as $category => $description) : ?>
                                            <?php if ($discovery_stats['by_category'][$category] > 0) : ?>
                                                <tr>
                                                    <td><?php echo esc_html(ucfirst($category)); ?></td>
                                                    <td><?php echo esc_html($discovery_stats['by_category'][$category]); ?></td>
                                                    <td><?php echo esc_html(round(($discovery_stats['by_category'][$category] / $discovery_stats['total_discovered']) * 100, 1)); ?>%</td>
                                                    <td><?php echo esc_html($description); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Blocked Cookies Statistics (if any) -->
                            <?php if (!empty($blocked_cookies)) : ?>
                            <div class="cookie-stats">
                                <h4><?php _e('Blocked Cookies Statistics', 'tfm-cookie-consent'); ?></h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Category', 'tfm-cookie-consent'); ?></th>
                                            <th><?php _e('Count', 'tfm-cookie-consent'); ?></th>
                                            <th><?php _e('Percentage', 'tfm-cookie-consent'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cookie_stats['by_category'] as $category => $count) : ?>
                                            <?php if ($count > 0) : ?>
                                                <tr>
                                                    <td><?php echo esc_html(ucfirst($category)); ?></td>
                                                    <td><?php echo esc_html($count); ?></td>
                                                    <td><?php echo esc_html(round(($count / $cookie_stats['total_blocked']) * 100, 1)); ?>%</td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Detailed Discovered Cookies List -->
                            <h4><?php _e('All Discovered Cookies', 'tfm-cookie-consent'); ?></h4>
                            <p><em><?php _e('This list shows all cookies that have been detected on your website, categorized by type. Use this information for your privacy policy.', 'tfm-cookie-consent'); ?></em></p>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Cookie Name', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Category', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Source', 'tfm-cookie-consent'); ?></th>
                                        <th><?php _e('Discovered At', 'tfm-cookie-consent'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($discovered_cookies as $cookie) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html($cookie['name']); ?></code></td>
                                            <td>
                                                <span class="cookie-category-badge cookie-category-<?php echo esc_attr($cookie['category']); ?>">
                                                    <?php echo esc_html(ucfirst($cookie['category'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html(ucfirst($cookie['source'])); ?></td>
                                            <td><?php echo esc_html(date('Y-m-d H:i:s', $cookie['discovered_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <p>
                                <button type="button" id="clear-discovered-cookies" class="button button-secondary">
                                    <?php _e('Clear Discovered Cookies', 'tfm-cookie-consent'); ?>
                                </button>
                                <button type="button" id="export-cookie-list" class="button button-secondary">
                                    <?php _e('Export Cookie List', 'tfm-cookie-consent'); ?>
                                </button>
                            </p>
                        <?php else : ?>
                            <p><?php _e('No cookies have been discovered yet.', 'tfm-cookie-consent'); ?></p>
                            <p><em><?php _e('Cookies will appear here once they are detected on your website. This information is essential for creating accurate privacy policies.', 'tfm-cookie-consent'); ?></em></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Advanced Tab -->
                    <div id="advanced" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="debug_mode"><?php _e('Debug Mode', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="debug_mode" name="tfm_cookie_consent_settings[debug_mode]" value="1" <?php checked(isset($settings['debug_mode']) ? $settings['debug_mode'] : false); ?>>
                                    <p class="description"><?php _e('Enable debug mode to show console logs for cookie blocking and consent management. Only enable for troubleshooting.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="custom_scripts"><?php _e('Custom Scripts', 'tfm-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <textarea id="custom_scripts" name="tfm_cookie_consent_settings[custom_scripts]" rows="10" class="large-text code"><?php echo esc_textarea(isset($settings['custom_scripts']) ? $settings['custom_scripts'] : ''); ?></textarea>
                                    <p class="description"><?php _e('Add custom JavaScript that should run when cookies are accepted. Use {category} to check specific category consent.', 'tfm-cookie-consent'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render category fields
     */
    private function render_category_fields($key, $category) {
        ?>
        <div class="cookie-category" data-key="<?php echo esc_attr($key); ?>">
            <h3><?php echo esc_html($category['name']); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="category_<?php echo esc_attr($key); ?>_name"><?php _e('Category Name', 'tfm-cookie-consent'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="category_<?php echo esc_attr($key); ?>_name" name="tfm_cookie_consent_settings[cookie_categories][<?php echo esc_attr($key); ?>][name]" value="<?php echo esc_attr($category['name']); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="category_<?php echo esc_attr($key); ?>_description"><?php _e('Description', 'tfm-cookie-consent'); ?></label>
                    </th>
                    <td>
                        <textarea id="category_<?php echo esc_attr($key); ?>_description" name="tfm_cookie_consent_settings[cookie_categories][<?php echo esc_attr($key); ?>][description]" rows="3" class="large-text"><?php echo esc_textarea($category['description']); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="category_<?php echo esc_attr($key); ?>_required"><?php _e('Required', 'tfm-cookie-consent'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="category_<?php echo esc_attr($key); ?>_required" name="tfm_cookie_consent_settings[cookie_categories][<?php echo esc_attr($key); ?>][required]" value="1" <?php checked($category['required']); ?>>
                        <p class="description"><?php _e('Required categories cannot be disabled by users.', 'tfm-cookie-consent'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="category_<?php echo esc_attr($key); ?>_enabled"><?php _e('Enabled by Default', 'tfm-cookie-consent'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="category_<?php echo esc_attr($key); ?>_enabled" name="tfm_cookie_consent_settings[cookie_categories][<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($category['enabled']); ?>>
                        <p class="description"><?php _e('Whether this category is enabled by default in the customize view.', 'tfm-cookie-consent'); ?></p>
                    </td>
                </tr>
            </table>
            <button type="button" class="button remove-category"><?php _e('Remove Category', 'tfm-cookie-consent'); ?></button>
        </div>
        <?php
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // General settings
        $sanitized['enabled'] = isset($input['enabled']) ? true : false;
        $sanitized['expiry_days'] = absint($input['expiry_days']);
        
        // Popup settings
        $sanitized['popup_title'] = sanitize_text_field($input['popup_title']);
        $sanitized['popup_message'] = wp_kses_post($input['popup_message']);
        $sanitized['accept_button_text'] = sanitize_text_field($input['accept_button_text']);
        $sanitized['deny_button_text'] = sanitize_text_field($input['deny_button_text']);
        $sanitized['customize_button_text'] = sanitize_text_field($input['customize_button_text']);
        $sanitized['popup_position'] = sanitize_text_field($input['popup_position']);
        $sanitized['popup_theme'] = sanitize_text_field($input['popup_theme']);
        
        // Color settings
        $sanitized['background_color'] = sanitize_hex_color($input['background_color']);
        $sanitized['text_color'] = sanitize_hex_color($input['text_color']);
        $sanitized['secondary_text_color'] = sanitize_hex_color($input['secondary_text_color']);
        $sanitized['border_color'] = sanitize_hex_color($input['border_color']);
        $sanitized['accept_button_color'] = sanitize_hex_color($input['accept_button_color']);
        $sanitized['accept_button_text_color'] = sanitize_hex_color($input['accept_button_text_color']);
        $sanitized['deny_button_color'] = sanitize_hex_color($input['deny_button_color']);
        $sanitized['deny_button_text_color'] = sanitize_hex_color($input['deny_button_text_color']);
        $sanitized['customize_button_color'] = sanitize_hex_color($input['customize_button_color']);
        $sanitized['customize_button_text_color'] = sanitize_hex_color($input['customize_button_text_color']);
        $sanitized['save_button_color'] = sanitize_hex_color($input['save_button_color']);
        $sanitized['save_button_text_color'] = sanitize_hex_color($input['save_button_text_color']);
        $sanitized['back_button_color'] = sanitize_hex_color($input['back_button_color']);
        $sanitized['back_button_text_color'] = sanitize_hex_color($input['back_button_text_color']);
        $sanitized['toggle_active_color'] = sanitize_hex_color($input['toggle_active_color']);
        $sanitized['toggle_inactive_color'] = sanitize_hex_color($input['toggle_inactive_color']);
        $sanitized['category_background_color'] = sanitize_hex_color($input['category_background_color']);
        $sanitized['overlay_color'] = sanitize_hex_color($input['overlay_color']);
        $sanitized['overlay_opacity'] = absint($input['overlay_opacity']);
        
        // Cookie categories
        if (isset($input['cookie_categories']) && is_array($input['cookie_categories'])) {
            foreach ($input['cookie_categories'] as $key => $category) {
                $sanitized['cookie_categories'][$key] = array(
                    'name' => sanitize_text_field($category['name']),
                    'description' => wp_kses_post($category['description']),
                    'required' => isset($category['required']) ? true : false,
                    'enabled' => isset($category['enabled']) ? true : false
                );
            }
        }
        
        // Integrations
        $sanitized['google_analytics'] = isset($input['google_analytics']) ? true : false;
        $sanitized['google_tag_manager'] = isset($input['google_tag_manager']) ? true : false;
        $sanitized['facebook_pixel'] = isset($input['facebook_pixel']) ? true : false;
        
        // Advanced
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? true : false;
        $sanitized['custom_scripts'] = wp_kses($input['custom_scripts'], array());
        
        return $sanitized;
    }
    
    /**
     * Save cookie settings via AJAX
     */
    public function save_cookie_settings() {
        check_ajax_referer('tfm_cookie_consent_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tfm-cookie-consent'));
        }
        
        $settings = $this->sanitize_settings($_POST['settings']);
        update_option('tfm_cookie_consent_settings', $settings);
        
        wp_send_json_success(__('Settings saved successfully!', 'tfm-cookie-consent'));
    }
    
    /**
     * Clear blocked cookies via AJAX
     */
    public function clear_blocked_cookies() {
        check_ajax_referer('tfm_cookie_consent_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tfm-cookie-consent'));
        }
        
        delete_option('tfm_cookie_consent_blocked_cookies');
        
        wp_send_json_success(__('Blocked cookies cleared successfully!', 'tfm-cookie-consent'));
    }
    
    /**
     * Export analytics data via AJAX
     */
    public function export_analytics() {
        check_ajax_referer('tfm_cookie_consent_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tfm-cookie-consent'));
        }
        
        $export_data = array(
            'export_date' => current_time('mysql'),
            'plugin_version' => TFM_COOKIE_CONSENT_VERSION,
            'consent_statistics' => TFM_Cookie_Consent_Settings::get_statistics(),
            'cookie_statistics' => TFM_Cookie_Consent_Cookies::get_cookie_statistics(),
            'consent_log' => TFM_Cookie_Consent_Settings::get_consent_log(),
            'blocked_cookies' => get_option('tfm_cookie_consent_blocked_cookies', array()),
            'settings' => get_option('tfm_cookie_consent_settings', array())
        );
        
        wp_send_json_success($export_data);
    }

    /**
     * Clear discovered cookies via AJAX
     */
    public function clear_discovered_cookies() {
        check_ajax_referer('tfm_cookie_consent_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tfm-cookie-consent'));
        }

        delete_option('tfm_cookie_consent_discovered_cookies');
        delete_option('tfm_cookie_consent_cookie_discovery_stats');

        wp_send_json_success(__('Discovered cookies cleared successfully!', 'tfm-cookie-consent'));
    }

    /**
     * Export cookie list via AJAX
     */
    public function export_cookie_list() {
        check_ajax_referer('tfm_cookie_consent_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tfm-cookie-consent'));
        }

        $discovered_cookies = get_option('tfm_cookie_consent_discovered_cookies', array());
        $discovery_stats = get_option('tfm_cookie_consent_cookie_discovery_stats', array());

        $export_data = array(
            'export_date' => current_time('mysql'),
            'plugin_version' => TFM_COOKIE_CONSENT_VERSION,
            'discovered_cookies' => $discovered_cookies,
            'discovery_stats' => $discovery_stats
        );

        $filename = 'tfm-cookie-consent-cookie-list-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
} 