<?php
/**
 * TFM Plugin Updater Class
 * Handles GitHub-based updates for the plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TFM_Updater {
    private static $initialized = false;
    private static $puc_loaded = false;
    private static $instance = null;
    private $update_checker;
    private $plugin_slug;
    private $github_url;
    private $github_branch;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Prevent duplicate initialization
        if (self::$initialized) {
            return;
        }
        
        // Configure the update source. The defaults keep the current repo, but the
        // repo URL and branch are overridable WITHOUT editing plugin code, so the
        // canonical repo can move (e.g. to the TFM account) as a config change:
        //   - Constants in wp-config.php: TFM_UPDATE_REPO, TFM_UPDATE_BRANCH
        //   - Filters: 'tfm_update_repo', 'tfm_update_branch' (take final precedence)
        // The plugin slug is tied to the installed plugin folder, not the repo, so
        // it stays fixed across a repo move.
        $this->plugin_slug = 'tfm-plugin-main';

        $default_repo   = 'https://github.com/TopFireMedia/tfm-plugin';
        $default_branch = 'main';

        $repo   = (defined('TFM_UPDATE_REPO') && TFM_UPDATE_REPO) ? TFM_UPDATE_REPO : $default_repo;
        $branch = (defined('TFM_UPDATE_BRANCH') && TFM_UPDATE_BRANCH) ? TFM_UPDATE_BRANCH : $default_branch;

        $this->github_url    = apply_filters('tfm_update_repo', $repo);
        $this->github_branch = apply_filters('tfm_update_branch', $branch);

        // Initialize the update checker
        $this->init_update_checker();
        
        // Mark as initialized
        self::$initialized = true;
    }

    private function init_update_checker() {
        // Check if the update checker class exists
        if (!class_exists('Puc_v4_Factory')) {
            
            $update_checker_file = plugin_dir_path(dirname(__FILE__)) . 'includes/plugin-update-checker/plugin-update-checker.php';
            
            if (file_exists($update_checker_file)) {
                require_once $update_checker_file;
            } else {
                return;
            }
        }

        try {
            
            $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5p6\PucFactory::buildUpdateChecker(
                $this->github_url,
                plugin_dir_path(dirname(__FILE__)) . 'topfiremedia.php',
                $this->plugin_slug
            );

            // Authenticate only if a token is configured. The default source repo
            // (TopFireMedia/tfm-plugin) is public, so no token is needed; a token is
            // required only when pointing at a private repo. Sourced from a
            // wp-config.php constant (preferred) or the plugin settings option —
            // never hardcoded. See get_github_token().
            $github_token = $this->get_github_token();
            if ($github_token) {
                $this->update_checker->setAuthentication($github_token);
            }

            // Set the branch
            $this->update_checker->setBranch($this->github_branch);

            // Add custom update message
            add_filter('puc_pre_inject_update-' . $this->plugin_slug, [$this, 'add_update_message'], 10, 2);
            
            // Add custom update notification
            add_action('admin_notices', [$this, 'show_update_notice']);

        } catch (Exception $e) {
            // Log the error
            error_log('TFM Updater Error: ' . $e->getMessage());
        }
    }

    public function add_update_message($update, $api_data = null) {
        if (isset($update->sections['changelog'])) {
            $update->sections['changelog'] = $this->format_changelog($update->sections['changelog']);
        }
        return $update;
    }

    private function format_changelog($changelog) {
        // Format the changelog for better readability
        $changelog = str_replace('###', '<h3>', $changelog);
        $changelog = str_replace('##', '<h2>', $changelog);
        $changelog = str_replace('#', '<h1>', $changelog);
        $changelog = preg_replace('/\n- /', '<br>- ', $changelog);
        return $changelog;
    }

    /**
     * Retrieve the GitHub authentication token for update checks.
     *
     * Credentials are never hardcoded. The default source repo is public, so no
     * token is required and this returns ''. A token is only needed when the
     * update source is a private repo: define TFM_GITHUB_TOKEN in wp-config.php
     * (recommended), or store it under the 'github_token' key in the plugin
     * settings option.
     *
     * @return string The token, or '' if none is configured.
     */
    private function get_github_token() {
        if (defined('TFM_GITHUB_TOKEN') && TFM_GITHUB_TOKEN) {
            return TFM_GITHUB_TOKEN;
        }

        $settings = get_option('tfm_plugin_settings', []);
        if (!empty($settings['github_token'])) {
            return $settings['github_token'];
        }

        return '';
    }

    public function show_update_notice() {
        if (!$this->update_checker) {
            return;
        }

        $update = $this->update_checker->getUpdate();
        if ($update) {
            $current_version = TFM_PLUGIN_VERSION;
            if (version_compare($current_version, $update->version, '<')) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong>TFM Custom Functions:</strong> A new version (<?php echo esc_html($update->version); ?>) is available.
                        <a href="<?php echo esc_url(admin_url('plugins.php')); ?>">View details</a>
                    </p>
                </div>
                <?php
            }
        }
    }
} 