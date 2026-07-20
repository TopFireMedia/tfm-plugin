<?php
/**
 * TFM Video Defer Class
 * Handles video container detection and deferring for Elementor and Divi
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TFM_Video_Defer {
    private $settings;
    private $error_log = [];
    private $performance_metrics = [];
    private $compatibility_info = [];

    public function __construct() {
        $this->init();
    }

    private function init() {
        // Load settings
        $this->load_settings();

        // Initialize hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'check_compatibility']);
        add_action('admin_notices', [$this, 'show_compatibility_notices']);

        // Add settings tab
        add_action('admin_init', function() {
            add_filter('tfm_settings_tabs', [$this, 'add_settings_tab']);
            add_action('tfm_render_settings_tab_video_defer', [$this, 'render_settings_tab']);
        });
    }

    private function load_settings() {
        $plugin_settings = get_option('tfm_plugin_settings', []);
        $this->settings = isset($plugin_settings['video_defer']) ? $plugin_settings['video_defer'] : [
            'enabled' => true,
            'elementor_enabled' => true,
            'divi_enabled' => true
        ];
    }

    public function enqueue_scripts() {
        if (!$this->settings['enabled']) {
            return;
        }

        // Enqueue main script
        wp_enqueue_script(
            'tfm-video-defer',
            TFM_PLUGIN_URL . 'assets/js/video-defer.js',
            ['jquery'],
            TFM_PLUGIN_VERSION,
            true
        );

        // Pass settings to script
        wp_localize_script('tfm-video-defer', 'tfmVideoDefer', [
            'settings' => $this->settings,
            'selectors' => $this->get_container_selectors()
        ]);
    }

    private function get_container_selectors() {
        $selectors = [];

        if ($this->settings['elementor_enabled']) {
            $selectors['elementor'] = [
                '.elementor-widget-video',
                '.elementor-widget-container'
            ];
        }

        if ($this->settings['divi_enabled']) {
            $selectors['divi'] = [
                '.et_pb_video',
                '.et_pb_module'
            ];
        }

        return $selectors;
    }

    public function check_compatibility() {
        $this->compatibility_info = [
            'elementor' => $this->check_elementor_compatibility(),
            'divi' => $this->check_divi_compatibility()
        ];
    }

    private function check_elementor_compatibility() {
        if (!defined('ELEMENTOR_PRO_VERSION')) {
            return [
                'compatible' => false,
                'message' => 'Elementor Pro is not active'
            ];
        }

        $version = ELEMENTOR_PRO_VERSION;
        $min_version = '3.0.0';

        return [
            'compatible' => version_compare($version, $min_version, '>='),
            'version' => $version,
            'min_version' => $min_version,
            'message' => version_compare($version, $min_version, '>=') 
                ? "Elementor Pro {$version} is compatible" 
                : "Elementor Pro {$version} is below minimum required version {$min_version}"
        ];
    }

    private function check_divi_compatibility() {
        if (!defined('ET_BUILDER_VERSION')) {
            return [
                'compatible' => false,
                'message' => 'Divi is not active'
            ];
        }

        $version = ET_BUILDER_VERSION;
        $min_version = '4.0.0';

        return [
            'compatible' => version_compare($version, $min_version, '>='),
            'version' => $version,
            'min_version' => $min_version,
            'message' => version_compare($version, $min_version, '>=') 
                ? "Divi {$version} is compatible" 
                : "Divi {$version} is below minimum required version {$min_version}"
        ];
    }

    public function show_compatibility_notices() {
        if (!$this->settings['enabled']) {
            return;
        }

        foreach ($this->compatibility_info as $type => $info) {
            // Only show notices for enabled builders
            if ($type === 'elementor' && !$this->settings['elementor_enabled']) {
                continue;
            }
            if ($type === 'divi' && !$this->settings['divi_enabled']) {
                continue;
            }

            if (!$info['compatible']) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong>TFM Video Defer:</strong> 
                        <?php echo esc_html($info['message']); ?>
                    </p>
                </div>
                <?php
            }
        }
    }

    public function add_settings_tab($tabs) {
        $tabs['video_defer'] = 'Video Defer';
        return $tabs;
    }

    public function render_settings_tab() {
        $settings = $this->settings;
        ?>
        <div class="tfm-settings-section">
            <h2>Video Defer Settings</h2>
            <p class="tfm-settings-description">Configure video deferring settings for Elementor and Divi.</p>

            <table class="form-table">
                <tr>
                    <th>Enable Video Defer</th>
                    <td>
                        <label>
                            <input type="checkbox" name="tfm_plugin_settings[video_defer][enabled]" value="1" <?php checked($settings['enabled'], true); ?>>
                            Enable video deferring
                        </label>
                    </td>
                </tr>

                <tr>
                    <th>Builder Support</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="tfm_plugin_settings[video_defer][elementor_enabled]" value="1" <?php checked($settings['elementor_enabled'], true); ?>>
                                Enable Elementor Support
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="tfm_plugin_settings[video_defer][divi_enabled]" value="1" <?php checked($settings['divi_enabled'], true); ?>>
                                Enable Divi Support
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <div class="tfm-settings-section">
                <h3>Compatibility Status</h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Builder</th>
                            <th>Status</th>
                            <th>Version</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->compatibility_info as $type => $info): ?>
                        <tr>
                            <td><?php echo esc_html(ucfirst($type)); ?></td>
                            <td>
                                <?php if ($info['compatible']): ?>
                                    <span style="color: green;">✓ Compatible</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ Not Compatible</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (isset($info['version'])) {
                                    echo esc_html($info['version']);
                                } else {
                                    echo 'Not installed';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
} 