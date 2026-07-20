<?php
class Puc_v4p13_Plugin_UpdateChecker {
    protected $metadataUrl;
    protected $pluginFile;
    protected $slug;
    protected $checkPeriod;
    protected $metadata = null;
    protected $lastCheck = 0;
    protected $checkPeriodHours = 12;
    protected $optionName = 'external_updates-';
    protected $debugMode = false;

    public function __construct($metadataUrl, $pluginFile, $slug = '', $checkPeriod = 12, $optionName = '') {
        $this->metadataUrl = $metadataUrl;
        $this->pluginFile = $pluginFile;
        $this->slug = $slug;
        $this->checkPeriod = $checkPeriod;
        $this->optionName = $optionName ?: $this->optionName . $this->slug;
        
        $this->initHooks();
    }

    protected function initHooks() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'checkForUpdates'));
        add_filter('plugins_api', array($this, 'injectInfo'), 20, 3);
        add_action('admin_init', array($this, 'maybeCheckForUpdates'));
    }

    public function checkForUpdates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $this->lastCheck = time();
        $state = $this->getUpdateState();

        if ($state->update) {
            $transient->response[$this->pluginFile] = $state->update;
        }

        return $transient;
    }

    public function injectInfo($result, $action = null, $args = null) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || ($args->slug !== $this->slug)) {
            return $result;
        }

        $update = $this->getUpdate();
        if ($update) {
            return $update;
        }

        return $result;
    }

    public function maybeCheckForUpdates() {
        if (!$this->shouldCheckForUpdates()) {
            return;
        }

        $this->checkForUpdates(get_site_transient('update_plugins'));
    }

    protected function shouldCheckForUpdates() {
        $state = $this->getUpdateState();
        return (time() - $state->lastCheck) >= ($this->checkPeriod * 3600);
    }

    protected function getUpdateState() {
        $state = get_site_option($this->optionName, null);
        if (empty($state)) {
            $state = new stdClass();
            $state->lastCheck = 0;
            $state->checkedVersion = '';
            $state->update = null;
        }
        return $state;
    }

    public function getUpdate() {
        $state = $this->getUpdateState();
        if (!empty($state->update)) {
            return $state->update;
        }

        $update = $this->requestMetadata();
        if ($update) {
            $state->update = $update;
            update_site_option($this->optionName, $state);
        }

        return $update;
    }

    protected function requestMetadata() {
        $response = wp_remote_get($this->metadataUrl);
        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return null;
        }

        $metadata = json_decode($body);
        if (!$metadata || !isset($metadata->version)) {
            return null;
        }

        $currentVersion = $this->getInstalledVersion();
        if (version_compare($metadata->version, $currentVersion, '<=')) {
            return null;
        }

        return $metadata;
    }

    protected function getInstalledVersion() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $pluginData = get_plugin_data($this->pluginFile);
        return $pluginData['Version'];
    }

    public function setBranch($branch) {
        $this->metadataUrl = add_query_arg('branch', $branch, $this->metadataUrl);
    }
} 