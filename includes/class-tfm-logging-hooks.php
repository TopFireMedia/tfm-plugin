<?php
/**
 * TFM Logging Hooks Class
 * Handles all WordPress action hooks for logging
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TFM_Logging_Hooks {
    private $logger;

    public function __construct($logger) {
        $this->logger = $logger;
    }

    public function init() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // User Actions
        add_action('wp_login', [$this, 'log_user_login'], 10, 2);
        add_action('wp_logout', [$this, 'log_user_logout']);
        add_action('user_register', [$this, 'log_user_register']);
        add_action('profile_update', [$this, 'log_profile_update']);

        // Content Actions
        add_action('publish_post', [$this, 'log_post_published']);
        add_action('publish_page', [$this, 'log_page_published']);
        add_action('deleted_post', [$this, 'log_post_deleted']);
        add_action('post_updated', [$this, 'log_post_updated'], 10, 3);

        // Media Actions
        add_action('add_attachment', [$this, 'log_media_uploaded']);
        add_action('delete_attachment', [$this, 'log_media_deleted']);

        // Comment Actions
        add_action('comment_post', [$this, 'log_comment_posted'], 10, 3);
        add_action('delete_comment', [$this, 'log_comment_deleted']);

        // Plugin Actions
        add_action('activated_plugin', [$this, 'log_plugin_activated']);
        add_action('deactivated_plugin', [$this, 'log_plugin_deactivated']);
        add_action('delete_plugin', [$this, 'log_plugin_deleted']);

        // Theme Actions
        add_action('switch_theme', [$this, 'log_theme_switched'], 10, 2);

        // Widget Actions
        add_action('widget_update_callback', [$this, 'log_widget_updated'], 10, 4);

        // Menu Actions
        add_action('wp_update_nav_menu', [$this, 'log_menu_updated']);

        // Option Changes
        add_action('update_option', [$this, 'log_option_updated'], 10, 3);
    }

    private function log_action($action, $data = []) {
        if ($this->logger) {
            $this->logger->log_action($action, $data);
        }
    }

    // User Actions
    public function log_user_login($user_login, $user) {
        $this->log_action('user_login', [
            'user_login' => $user_login,
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'user_role' => $user->roles[0] ?? 'none'
        ]);
    }

    public function log_user_logout() {
        $user_id = get_current_user_id();
        $user = $user_id ? get_user_by('id', $user_id) : null;
        $this->log_action('user_logout', [
            'user_id' => $user_id,
            'user_login' => $user ? $user->user_login : '',
            'user_email' => $user ? $user->user_email : ''
        ]);
    }

    public function log_user_register($user_id) {
        $user = get_user_by('id', $user_id);
        $this->log_action('user_register', [
            'user_id' => $user_id,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'user_role' => $user->roles[0] ?? 'none'
        ]);
    }

    public function log_profile_update($user_id) {
        $user = get_user_by('id', $user_id);
        $this->log_action('user_profile_update', [
            'user_id' => $user_id,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email
        ]);
    }

    // Content Actions
    public function log_post_published($post_id) {
        $post = get_post($post_id);
        $this->log_action('post_published', [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'post_author' => $post->post_author,
            'post_status' => $post->post_status
        ]);
    }

    public function log_page_published($post_id) {
        $post = get_post($post_id);
        $this->log_action('page_published', [
            'page_id' => $post_id,
            'page_title' => $post->post_title,
            'page_author' => $post->post_author,
            'page_status' => $post->post_status
        ]);
    }

    public function log_post_deleted($post_id) {
        $post = get_post($post_id);
        if ($post) {
            $this->log_action('post_deleted', [
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'post_author' => $post->post_author
            ]);
        }
    }

    public function log_post_updated($post_id, $post_after, $post_before) {
        $this->log_action('post_updated', [
            'post_id' => $post_id,
            'post_title' => $post_after->post_title,
            'post_type' => $post_after->post_type,
            'post_author' => $post_after->post_author,
            'old_status' => $post_before->post_status,
            'new_status' => $post_after->post_status
        ]);
    }

    // Media Actions
    public function log_media_uploaded($post_id) {
        $post = get_post($post_id);
        $this->log_action('media_uploaded', [
            'attachment_id' => $post_id,
            'file_name' => $post->post_title,
            'file_type' => get_post_mime_type($post_id),
            'uploaded_by' => $post->post_author
        ]);
    }

    public function log_media_deleted($post_id) {
        $post = get_post($post_id);
        if ($post) {
            $this->log_action('media_deleted', [
                'attachment_id' => $post_id,
                'file_name' => $post->post_title,
                'file_type' => get_post_mime_type($post_id),
                'deleted_by' => get_current_user_id()
            ]);
        }
    }

    // Comment Actions
    public function log_comment_posted($comment_id, $comment_approved, $commentdata) {
        $this->log_action('comment_posted', [
            'comment_id' => $comment_id,
            'post_id' => $commentdata['comment_post_ID'],
            'comment_author' => $commentdata['comment_author'],
            'comment_author_email' => $commentdata['comment_author_email'],
            'comment_approved' => $comment_approved
        ]);
    }

    public function log_comment_deleted($comment_id) {
        $comment = get_comment($comment_id);
        if ($comment) {
            $this->log_action('comment_deleted', [
                'comment_id' => $comment_id,
                'post_id' => $comment->comment_post_ID,
                'comment_author' => $comment->comment_author,
                'deleted_by' => get_current_user_id()
            ]);
        }
    }

    // Plugin Actions
    public function log_plugin_activated($plugin) {
        $this->log_action('plugin_activated', [
            'plugin' => $plugin,
            'activated_by' => get_current_user_id()
        ]);
    }

    public function log_plugin_deactivated($plugin) {
        $this->log_action('plugin_deactivated', [
            'plugin' => $plugin,
            'deactivated_by' => get_current_user_id()
        ]);
    }

    public function log_plugin_deleted($plugin) {
        $this->log_action('plugin_deleted', [
            'plugin' => $plugin,
            'deleted_by' => get_current_user_id()
        ]);
    }

    // Theme Actions
    public function log_theme_switched($new_name, $new_theme) {
        $this->log_action('theme_switched', [
            'new_theme' => $new_name,
            'theme_object' => $new_theme->get_stylesheet(),
            'switched_by' => get_current_user_id()
        ]);
    }

    // Widget Actions
    public function log_widget_updated($instance, $new_instance, $old_instance, $widget) {
        $this->log_action('widget_updated', [
            'widget_id' => $widget->id,
            'widget_name' => $widget->name,
            'updated_by' => get_current_user_id()
        ]);
        return $instance;
    }

    // Menu Actions
    public function log_menu_updated($menu_id) {
        $menu = wp_get_nav_menu_object($menu_id);
        if ($menu) {
            $this->log_action('menu_updated', [
                'menu_id' => $menu_id,
                'menu_name' => $menu->name,
                'updated_by' => get_current_user_id()
            ]);
        }
    }

    // Option Changes
    public function log_option_updated($option_name, $old_value, $value) {
        // Skip logging if values are the same
        if ($old_value === $value) {
            return;
        }

        $changes = $this->analyze_option_changes($option_name, $old_value, $value);
        
        if (!empty($changes)) {
            $this->log_action('option_updated', [
                'option_name' => $option_name,
                'change_type' => $changes['type'],
                'changes' => $changes['changes'],
                'summary' => $changes['summary']
            ]);
        }
    }

    private function analyze_option_changes($option_name, $old_value, $new_value) {
        // Handle Elementor assets data
        if ($option_name === '_elementor_assets_data') {
            return $this->analyze_elementor_assets_changes($old_value, $new_value);
        }

        // Handle other option types
        if (is_array($old_value) && is_array($new_value)) {
            return $this->analyze_array_changes($option_name, $old_value, $new_value);
        }

        // Default handling for simple values
        return [
            'type' => 'simple_update',
            'changes' => [
                'old_value' => $this->summarize_value($old_value),
                'new_value' => $this->summarize_value($new_value)
            ],
            'summary' => sprintf('Updated %s from %s to %s', 
                $option_name, 
                $this->summarize_value($old_value), 
                $this->summarize_value($new_value)
            )
        ];
    }

    private function analyze_elementor_assets_changes($old_value, $new_value) {
        $changes = [
            'added' => [],
            'removed' => [],
            'modified' => []
        ];

        // Compare SVG icons
        if (isset($old_value['svg']['font-icon']) && isset($new_value['svg']['font-icon'])) {
            $old_icons = $old_value['svg']['font-icon'];
            $new_icons = $new_value['svg']['font-icon'];

            // Find added and removed icons
            foreach ($new_icons as $icon_name => $icon_data) {
                if (!isset($old_icons[$icon_name])) {
                    $changes['added'][] = $icon_name;
                } elseif ($old_icons[$icon_name] !== $icon_data) {
                    $changes['modified'][] = $icon_name;
                }
            }

            foreach ($old_icons as $icon_name => $icon_data) {
                if (!isset($new_icons[$icon_name])) {
                    $changes['removed'][] = $icon_name;
                }
            }
        }

        // Generate summary
        $summary_parts = [];
        if (!empty($changes['added'])) {
            $summary_parts[] = sprintf('Added %d icon(s): %s', 
                count($changes['added']), 
                implode(', ', $changes['added'])
            );
        }
        if (!empty($changes['removed'])) {
            $summary_parts[] = sprintf('Removed %d icon(s): %s', 
                count($changes['removed']), 
                implode(', ', $changes['removed'])
            );
        }
        if (!empty($changes['modified'])) {
            $summary_parts[] = sprintf('Modified %d icon(s): %s', 
                count($changes['modified']), 
                implode(', ', $changes['modified'])
            );
        }

        return [
            'type' => 'elementor_assets_update',
            'changes' => $changes,
            'summary' => implode('; ', $summary_parts)
        ];
    }

    private function analyze_array_changes($option_name, $old_value, $new_value) {
        $changes = [
            'added' => [],
            'removed' => [],
            'modified' => []
        ];

        // Compare array keys
        foreach ($new_value as $key => $value) {
            if (!isset($old_value[$key])) {
                $changes['added'][] = $key;
            } elseif ($old_value[$key] !== $value) {
                $changes['modified'][] = $key;
            }
        }

        foreach ($old_value as $key => $value) {
            if (!isset($new_value[$key])) {
                $changes['removed'][] = $key;
            }
        }

        // Generate summary
        $summary_parts = [];
        if (!empty($changes['added'])) {
            $summary_parts[] = sprintf('Added: %s', implode(', ', $changes['added']));
        }
        if (!empty($changes['removed'])) {
            $summary_parts[] = sprintf('Removed: %s', implode(', ', $changes['removed']));
        }
        if (!empty($changes['modified'])) {
            $summary_parts[] = sprintf('Modified: %s', implode(', ', $changes['modified']));
        }

        return [
            'type' => 'array_update',
            'changes' => $changes,
            'summary' => sprintf('Updated %s: %s', $option_name, implode('; ', $summary_parts))
        ];
    }

    private function summarize_value($value) {
        if (is_array($value)) {
            return sprintf('Array with %d items', count($value));
        }
        if (is_object($value)) {
            return get_class($value);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_string($value)) {
            return strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value;
        }
        return (string) $value;
    }
} 