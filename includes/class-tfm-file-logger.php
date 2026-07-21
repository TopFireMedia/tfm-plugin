<?php
/**
 * TFM File Logger Class
 * Handles logging WordPress actions to text files
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TFM_File_Logger {
    private $log_directory;
    private $current_log_file;
    private $max_log_size = 5242880; // 5MB
    private $error_log = [];
    private $settings;
    private $log_dir;
    private $log_file;

    public function __construct() {
        $this->settings = tfm_load_settings();
        
        // Ensure enable_logging is set
        if (!isset($this->settings['enable_logging'])) {
            $this->settings['enable_logging'] = false;
        }
        
        // Ensure log_retention_days is set
        if (!isset($this->settings['log_retention_days'])) {
            $this->settings['log_retention_days'] = 30;
        }
        
        // Ensure log_level is set (default to logging everything for a full audit trail)
        if (!isset($this->settings['log_level'])) {
            $this->settings['log_level'] = 'all';
        }
        
        $upload_dir = wp_upload_dir();
        $this->log_directory = trailingslashit($upload_dir['basedir']) . 'tfm-logs/';
        $this->current_log_file = $this->log_directory . 'wordpress-activity-' . date('Y-m') . '.log';
        
        // Create log directory if it doesn't exist
        if (!file_exists($this->log_directory)) {
            wp_mkdir_p($this->log_directory);
        }
        
        // Add .htaccess to protect logs
        $this->protect_log_directory();
    }

    private function protect_log_directory() {
        // Create .htaccess to prevent direct access
        $htaccess_file = $this->log_directory . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all\nRequire all denied\nOptions -Indexes\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.php to prevent directory listing
        $index_file = $this->log_directory . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden\n");
        }
    }

    public function init() {
        try {
            // Create logs directory if it doesn't exist
            if (!file_exists($this->log_directory)) {
                if (!wp_mkdir_p($this->log_directory)) {
                    throw new Exception('Failed to create log directory');
                }
            }

            // Verify directory permissions
            if (!is_writable($this->log_directory)) {
                throw new Exception('Log directory is not writable');
            }

            // Create .htaccess to prevent direct access
            $htaccess_file = $this->log_directory . '.htaccess';
            if (!file_exists($htaccess_file)) {
                $htaccess_content = "Order deny,allow\nDeny from all\nRequire all denied\nOptions -Indexes\n";
                if (file_put_contents($htaccess_file, $htaccess_content) === false) {
                    throw new Exception('Failed to create .htaccess file');
                }
            }

            // Create index.php to prevent directory listing
            $index_file = $this->log_directory . 'index.php';
            if (!file_exists($index_file)) {
                if (file_put_contents($index_file, "<?php\n// Silence is golden\n") === false) {
                    throw new Exception('Failed to create index.php file');
                }
            }
        } catch (Exception $e) {
            $this->error_log[] = $e->getMessage();
            error_log('TFM Logger Error: ' . $e->getMessage());
        }
    }

    public function log_action($action, $data = []) {
        if (!$this->settings['enable_logging']) {
            return false;
        }

        // Severity is assigned per action and stored on the entry. Events below the
        // configured log level are dropped here at write time (real filtering).
        $severity = self::get_action_severity($action);
        $configured_level = $this->settings['log_level'] ?? 'all';
        if (self::severity_rank($severity) < self::level_threshold($configured_level)) {
            return false;
        }

        if (!is_dir($this->log_directory) || !is_writable($this->log_directory)) {
            $this->error_log[] = 'Log directory is not writable: ' . $this->log_directory;
            error_log('TFM Logger Error: Log directory is not writable: ' . $this->log_directory);
            return false;
        }

        try {
            $actor = $this->get_actor();
            $ip = $this->get_client_ip();

            // Sanitize data
            $sanitized_data = $this->sanitize_log_data($data);

            $log_entry = [
                'timestamp' => current_time('mysql'),
                'action' => sanitize_text_field($action),
                'severity' => $severity,
                'user_id' => $actor['user_id'],
                'user_login' => $actor['user_login'],
                'user_display_name' => $actor['user_display_name'],
                'user_email' => $actor['user_email'],
                'user_role' => $actor['user_role'],
                'context' => $actor['context'],
                'ip_address' => sanitize_text_field($ip),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'),
                'data' => $sanitized_data
            ];

            $log_line = json_encode($log_entry);
            if ($log_line === false) {
                throw new Exception('Failed to encode log entry: ' . json_last_error_msg());
            }
            $log_line .= "\n";
            
            // Rotate log if necessary
            $this->maybe_rotate_log();

            if (file_put_contents($this->current_log_file, $log_line, FILE_APPEND | LOCK_EX) === false) {
                throw new Exception('Failed to write to log file: ' . $this->current_log_file);
            }

            return true;
        } catch (Exception $e) {
            $this->error_log[] = $e->getMessage();
            error_log('TFM Logger Error: ' . $e->getMessage());
            
            // Add admin notice for logging errors
            if (is_admin()) {
                add_action('admin_notices', function() use ($e) {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p>
                            <strong>TFM Logger Error:</strong> 
                            <?php echo esc_html($e->getMessage()); ?>
                        </p>
                    </div>
                    <?php
                });
            }
            return false;
        }
    }

    /**
     * Resolve who performed the action. When there's an authenticated user we
     * record their identity; otherwise we record the real execution context
     * (cron/wp-cli/rest/unauthenticated) instead of a blank "user 0", so an
     * automated or API-driven change can still be attributed to a source.
     *
     * @return array{user_id:int,user_login:string,user_display_name:string,user_email:string,user_role:string,context:string}
     */
    private function get_actor() {
        $user = wp_get_current_user();

        if ($user && $user->exists()) {
            return [
                'user_id'           => absint($user->ID),
                'user_login'        => sanitize_user($user->user_login),
                'user_display_name' => sanitize_text_field($user->display_name),
                'user_email'        => sanitize_email($user->user_email),
                'user_role'         => !empty($user->roles) ? sanitize_text_field($user->roles[0]) : '',
                'context'           => 'web',
            ];
        }

        // No authenticated user — identify the source explicitly.
        if (wp_doing_cron()) {
            $context = 'cron';
        } elseif (defined('WP_CLI') && WP_CLI) {
            $context = 'wp-cli';
        } elseif (defined('REST_REQUEST') && REST_REQUEST) {
            $context = 'rest';
        } else {
            $context = 'unauthenticated';
        }

        return [
            'user_id'           => 0,
            'user_login'        => $context,
            'user_display_name' => '',
            'user_email'        => '',
            'user_role'         => '',
            'context'           => $context,
        ];
    }

    /**
     * Canonical severity for an action (single source of truth for both
     * write-time filtering and the admin viewer's badge). Defaults to 'info'.
     */
    public static function get_action_severity($action) {
        static $map = [
            // Authentication
            'user_login'          => 'success',
            'user_login_failed'   => 'warning',
            'user_logout'         => 'info',
            // Users
            'user_register'       => 'success',
            'user_profile_update' => 'info',
            'user_role_changed'   => 'danger',
            'user_deleted'        => 'danger',
            // Content
            'post_published'      => 'success',
            'page_published'      => 'success',
            'post_status_changed' => 'info',
            'post_updated'        => 'info',
            'post_trashed'        => 'warning',
            'post_deleted'        => 'danger',
            // Media
            'media_uploaded'      => 'info',
            'media_deleted'       => 'warning',
            // Comments
            'comment_posted'      => 'info',
            'comment_deleted'     => 'warning',
            // Plugins / themes / core
            'plugin_activated'    => 'warning',
            'plugin_deactivated'  => 'warning',
            'plugin_deleted'      => 'danger',
            'plugin_updated'      => 'info',
            'theme_switched'      => 'warning',
            'theme_updated'       => 'info',
            'core_updated'        => 'warning',
            // Site structure
            'widget_updated'      => 'info',
            'menu_updated'        => 'info',
            'option_updated'      => 'warning',
        ];

        return isset($map[$action]) ? $map[$action] : 'info';
    }

    /** Numeric rank for a severity string (higher = more important). */
    private static function severity_rank($severity) {
        switch ($severity) {
            case 'danger':  return 3;
            case 'warning': return 2;
            case 'success':
            case 'info':
            default:        return 1;
        }
    }

    /** Minimum severity rank that the configured log level will record. */
    private static function level_threshold($level) {
        switch ($level) {
            case 'critical':
            case 'error':    // legacy value
                return 3;
            case 'important':
            case 'warning':  // legacy value
                return 2;
            case 'all':
            case 'info':     // legacy value
            default:
                return 1;
        }
    }

    private function sanitize_log_data($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->sanitize_log_data($value);
                } else {
                    $data[$key] = sanitize_text_field($value);
                }
            }
        }
        return $data;
    }

    private function get_client_ip() {
        $ip_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header]);
                return filter_var(trim($ip[0]), FILTER_VALIDATE_IP) ?: 'Unknown';
            }
        }

        return 'Unknown';
    }

    private function maybe_rotate_log() {
        if (!file_exists($this->current_log_file)) {
            return;
        }

        try {
            if (filesize($this->current_log_file) > $this->max_log_size) {
                $archive_name = $this->log_directory . 'wordpress-activity-' . date('Y-m-d-His') . '.log';
                if (!rename($this->current_log_file, $archive_name)) {
                    throw new Exception('Failed to rotate log file');
                }
            }
        } catch (Exception $e) {
            $this->error_log[] = $e->getMessage();
            error_log('TFM Logger Error: ' . $e->getMessage());
        }
    }

    public function get_logs($limit = 1000, $offset = 0) {
        $logs = [];
        $files = glob($this->log_directory . 'wordpress-activity-*.log');
        if ($files === false) {
            return $logs;
        }
        rsort($files); // Most recent first

        try {
            foreach ($files as $file) {
                if (count($logs) >= $limit) {
                    break;
                }

                $handle = fopen($file, 'r');
                if ($handle) {
                    while (($line = fgets($handle)) !== false) {
                        $log_entry = json_decode($line, true);
                        if ($log_entry === null) {
                            continue; // Skip invalid JSON
                        }
                        $logs[] = $log_entry;
                        if (count($logs) >= $limit) {
                            break;
                        }
                    }
                    fclose($handle);
                }
            }
        } catch (Exception $e) {
            $this->error_log[] = $e->getMessage();
            error_log('TFM Logger Error: ' . $e->getMessage());
        }

        return array_slice($logs, $offset, $limit);
    }

    public function purge_logs($days_to_keep = 30) {
        $files = glob($this->log_directory . 'wordpress-activity-*.log');
        if ($files === false) {
            return 0;
        }
        
        $cutoff = strtotime("-{$days_to_keep} days");
        $purged_count = 0;
        $current_month_file = 'wordpress-activity-' . date('Y-m') . '.log';

        try {
            foreach ($files as $file) {
                // Don't delete current month's log file
                if (basename($file) === $current_month_file) {
                    continue;
                }

                $should_delete = false;
                $handle = fopen($file, 'r');
                
                if ($handle) {
                    // Read first line to get earliest entry
                    $first_line = fgets($handle);
                    if ($first_line) {
                        $entry = json_decode($first_line, true);
                        if ($entry && isset($entry['timestamp'])) {
                            $entry_time = strtotime($entry['timestamp']);
                            if ($entry_time < $cutoff) {
                                $should_delete = true;
                            }
                        }
                    }
                    fclose($handle);
                }

                if ($should_delete) {
                    if (unlink($file)) {
                        $purged_count++;
                    }
                }
            }
        } catch (Exception $e) {
            $this->error_log[] = $e->getMessage();
            error_log('TFM Logger Error: ' . $e->getMessage());
        }

        return $purged_count;
    }

    public function get_errors() {
        return $this->error_log;
    }

    public function clear_errors() {
        $this->error_log = [];
    }

    public function get_log_directory() {
        return $this->log_directory;
    }

    public function get_current_log_file() {
        return $this->current_log_file;
    }
} 