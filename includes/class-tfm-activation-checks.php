<?php
/**
 * TFM Activation Checks Class
 * Handles all pre-activation checks for the plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TFM_Activation_Checks {
    private $min_wp_version = '5.0.0';
    private $min_php_version = '7.0.0';
    private $required_extensions = ['json', 'mbstring'];
    private $errors = [];
    private $error_log = [];

    /**
     * Run all activation checks
     * 
     * @return bool True if all checks pass, false otherwise
     */
    public function run_checks() {
        // Set up error handling
        $this->setup_error_handling();
        
        // Run all checks
        $this->check_wordpress_version();
        $this->check_php_version();
        $this->check_php_extensions();
        $this->check_file_permissions();
        $this->check_wordpress_functions();
        $this->check_php_errors();
        
        // Restore error handling
        $this->restore_error_handling();

        return empty($this->errors);
    }

    /**
     * Get all activation errors
     * 
     * @return array Array of error messages
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Set up custom error handling
     */
    private function setup_error_handling() {
        // Store current error reporting level
        $this->error_reporting = error_reporting();
        
        // Set error handler
        set_error_handler([$this, 'custom_error_handler']);
        
        // Enable all error reporting
        error_reporting(E_ALL);
        
        // Start output buffering to catch any errors
        ob_start();
    }

    /**
     * Restore original error handling
     */
    private function restore_error_handling() {
        // Get any output that might contain errors
        $output = ob_get_clean();
        
        // Restore original error reporting
        error_reporting($this->error_reporting);
        
        // Restore original error handler
        restore_error_handler();
        
        // Check if there was any output (which might indicate errors)
        if (!empty($output)) {
            $this->error_log[] = 'Unexpected output during activation: ' . $output;
        }
    }

    /**
     * Custom error handler
     */
    public function custom_error_handler($errno, $errstr, $errfile, $errline) {
        $error_type = $this->get_error_type($errno);
        $this->error_log[] = sprintf(
            'PHP %s: %s in %s on line %d',
            $error_type,
            $errstr,
            $errfile,
            $errline
        );
        return true; // Prevent PHP from handling the error internally
    }

    /**
     * Get error type string from error number
     */
    private function get_error_type($errno) {
        switch ($errno) {
            case E_ERROR:
                return 'Fatal Error';
            case E_WARNING:
                return 'Warning';
            case E_PARSE:
                return 'Parse Error';
            case E_NOTICE:
                return 'Notice';
            case E_CORE_ERROR:
                return 'Core Error';
            case E_CORE_WARNING:
                return 'Core Warning';
            case E_COMPILE_ERROR:
                return 'Compile Error';
            case E_COMPILE_WARNING:
                return 'Compile Warning';
            case E_USER_ERROR:
                return 'User Error';
            case E_USER_WARNING:
                return 'User Warning';
            case E_USER_NOTICE:
                return 'User Notice';
            case E_STRICT:
                return 'Strict Standards';
            case E_RECOVERABLE_ERROR:
                return 'Recoverable Error';
            case E_DEPRECATED:
                return 'Deprecated';
            case E_USER_DEPRECATED:
                return 'User Deprecated';
            default:
                return 'Unknown Error';
        }
    }

    /**
     * Check for PHP errors during activation
     */
    private function check_php_errors() {
        if (!empty($this->error_log)) {
            $this->errors[] = 'PHP errors were detected during activation:';
            foreach ($this->error_log as $error) {
                $this->errors[] = ' - ' . $error;
            }
        }
    }

    /**
     * Check WordPress version
     */
    private function check_wordpress_version() {
        global $wp_version;
        if (version_compare($wp_version, $this->min_wp_version, '<')) {
            $this->errors[] = sprintf(
                'This plugin requires WordPress version %s or higher. You are running version %s.',
                $this->min_wp_version,
                $wp_version
            );
        }
    }

    /**
     * Check PHP version
     */
    private function check_php_version() {
        if (version_compare(PHP_VERSION, $this->min_php_version, '<')) {
            $this->errors[] = sprintf(
                'This plugin requires PHP version %s or higher. You are running version %s.',
                $this->min_php_version,
                PHP_VERSION
            );
        }
    }

    /**
     * Check required PHP extensions
     */
    private function check_php_extensions() {
        foreach ($this->required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $this->errors[] = sprintf(
                    'This plugin requires the PHP %s extension to be installed and enabled.',
                    $extension
                );
            }
        }
    }

    /**
     * Check file permissions for logging
     */
    private function check_file_permissions() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/tfm-logs';

        // Check if directory exists, if not try to create it
        if (!file_exists($log_dir)) {
            if (!wp_mkdir_p($log_dir)) {
                $this->errors[] = 'Unable to create log directory. Please check your file permissions.';
                return;
            }
        }

        // Check if directory is writable
        if (!is_writable($log_dir)) {
            $this->errors[] = 'Log directory is not writable. Please check your file permissions.';
        }
    }

    /**
     * Check required WordPress functions and classes
     */
    private function check_wordpress_functions() {
        $required_functions = [
            'add_action',
            'add_filter',
            'wp_enqueue_script',
            'wp_register_script',
            'get_option',
            'update_option'
        ];

        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                $this->errors[] = sprintf(
                    'Required WordPress function %s() is not available.',
                    $function
                );
            }
        }
    }
} 