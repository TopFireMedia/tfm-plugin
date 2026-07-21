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
        // Authentication
        add_action('wp_login', [$this, 'log_user_login'], 10, 2);
        add_action('wp_login_failed', [$this, 'log_login_failed']);
        add_action('wp_logout', [$this, 'log_user_logout']);

        // User account changes
        add_action('user_register', [$this, 'log_user_register']);
        add_action('profile_update', [$this, 'log_profile_update']);
        add_action('set_user_role', [$this, 'log_user_role_changed'], 10, 3);
        add_action('delete_user', [$this, 'log_user_deleted'], 10, 3);

        // Content — one status-transition handler covers ALL post types
        // (publish/draft/pending/trash), skipping autosaves and revisions.
        add_action('transition_post_status', [$this, 'log_post_transition'], 10, 3);
        add_action('post_updated', [$this, 'log_post_updated'], 10, 3);
        add_action('before_delete_post', [$this, 'log_post_deleted']);

        // Page-builder edits: Elementor saves its design to postmeta, so a
        // design-only change never trips post_updated. This hook fires only when
        // Elementor is active. (Divi keeps its layout in post_content and is
        // already covered by post_updated.)
        add_action('elementor/document/after_save', [$this, 'log_elementor_save'], 10, 2);

        // Media Actions
        add_action('add_attachment', [$this, 'log_media_uploaded']);
        add_action('delete_attachment', [$this, 'log_media_deleted']);

        // Comment Actions
        add_action('comment_post', [$this, 'log_comment_posted'], 10, 3);
        add_action('delete_comment', [$this, 'log_comment_deleted']);

        // Plugin / theme / core changes
        add_action('activated_plugin', [$this, 'log_plugin_activated']);
        add_action('deactivated_plugin', [$this, 'log_plugin_deactivated']);
        add_action('deleted_plugin', [$this, 'log_plugin_deleted'], 10, 2);
        add_action('switch_theme', [$this, 'log_theme_switched'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'log_upgrade_completed'], 10, 2);

        // Widget Actions
        add_action('widget_update_callback', [$this, 'log_widget_updated'], 10, 4);

        // Menu Actions
        add_action('wp_update_nav_menu', [$this, 'log_menu_updated']);

        // Security-relevant option changes only (allowlist inside the handler)
        add_action('updated_option', [$this, 'log_option_updated'], 10, 3);
    }

    /**
     * Options worth logging — site identity, membership/registration, active
     * theme/plugins, permalinks, search-engine visibility. Everything else
     * (transients, cron, caches, routine plugin data) is ignored, which is what
     * keeps the log a signal instead of noise.
     */
    private function is_loggable_option($option_name) {
        // Note: active_plugins / template / stylesheet are intentionally omitted —
        // plugin and theme changes are captured more precisely by the dedicated
        // activated_plugin / switch_theme / upgrader hooks, so logging the option
        // too would double-record every toggle.
        static $allowlist = [
            'siteurl', 'home', 'blogname', 'admin_email', 'blogdescription',
            'users_can_register', 'default_role', 'blog_public', 'permalink_structure',
            'WPLANG', 'timezone_string', 'date_format', 'time_format',
            'default_comment_status', 'require_name_email', 'comment_moderation',
        ];

        $allowlist = apply_filters('tfm_loggable_options', $allowlist);

        return in_array($option_name, $allowlist, true);
    }

    private function log_action($action, $data = [], $actor = null) {
        if ($this->logger) {
            $this->logger->log_action($action, $data, $actor);
        }
    }

    // User Actions
    public function log_user_login($user_login, $user) {
        // The just-logged-in user isn't the "current" user yet on this request,
        // so pass $user explicitly to attribute the entry to them.
        $this->log_action('user_login', [
            'user_login' => $user_login,
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'user_role' => $user->roles[0] ?? 'none'
        ], $user);
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
            'user_login' => $user ? $user->user_login : '',
            'user_email' => $user ? $user->user_email : ''
        ]);
    }

    public function log_login_failed($username) {
        $this->log_action('user_login_failed', [
            'attempted_username' => $username,
        ]);
    }

    public function log_user_role_changed($user_id, $new_role, $old_roles) {
        // set_user_role also fires while creating a user (empty previous roles).
        // That's a registration, not a role change — user_register covers it.
        if (empty($old_roles)) {
            return;
        }
        $user = get_user_by('id', $user_id);
        $this->log_action('user_role_changed', [
            'user_id'    => $user_id,
            'user_login' => $user ? $user->user_login : '',
            'new_role'   => $new_role,
            'old_roles'  => is_array($old_roles) ? implode(', ', $old_roles) : (string) $old_roles,
        ]);
    }

    /** Fires on delete_user (before deletion) so the target's identity is captured. */
    public function log_user_deleted($user_id, $reassign = null, $user = null) {
        if (!$user instanceof WP_User) {
            $user = get_user_by('id', $user_id);
        }
        $this->log_action('user_deleted', [
            'user_id'       => $user_id,
            'user_login'    => $user ? $user->user_login : '',
            'user_email'    => $user ? $user->user_email : '',
            'reassigned_to' => $reassign ? $reassign : 'none',
        ]);
    }

    // Content Actions

    /**
     * Single handler for all post-type status transitions (publish, trash,
     * draft, pending…), for every post type. Skips revisions/autosaves and the
     * auto-draft/placeholder churn so only real events are recorded.
     */
    public function log_post_transition($new_status, $old_status, $post) {
        if (!$post instanceof WP_Post) {
            return;
        }
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }
        // Ignore internal/placeholder types and non-events.
        $ignore_types = ['revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'attachment'];
        if (in_array($post->post_type, $ignore_types, true)) {
            return;
        }
        if (in_array($new_status, ['auto-draft', 'inherit'], true)) {
            return;
        }

        // First publish is a distinct, notable event.
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->log_action('post_published', [
                'post_id'     => $post->ID,
                'post_title'  => $post->post_title,
                'post_type'   => $post->post_type,
                'post_author' => $post->post_author,
                'post_status' => $new_status,
            ]);
            return;
        }

        // Sent to trash.
        if ($new_status === 'trash') {
            $this->log_action('post_trashed', [
                'post_id'    => $post->ID,
                'post_title' => $post->post_title,
                'post_type'  => $post->post_type,
            ]);
            return;
        }

        // Any other real status change (e.g. published → draft, pending → publish).
        if ($new_status !== $old_status) {
            $this->log_action('post_status_changed', [
                'post_id'    => $post->ID,
                'post_title' => $post->post_title,
                'post_type'  => $post->post_type,
                'old_status' => $old_status,
                'new_status' => $new_status,
            ]);
        }
    }

    /**
     * Content edits to an existing post where the STATUS did not change (status
     * changes are handled by log_post_transition). Records which fields changed.
     */
    public function log_post_updated($post_id, $post_after, $post_before) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        $ignore_types = ['revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'attachment'];
        if (in_array($post_after->post_type, $ignore_types, true)) {
            return;
        }
        // Status changes are logged by log_post_transition; only handle same-status edits.
        if ($post_before->post_status !== $post_after->post_status) {
            return;
        }
        if (in_array($post_after->post_status, ['auto-draft', 'inherit', 'trash'], true)) {
            return;
        }

        $changed = [];
        if ($post_before->post_title !== $post_after->post_title)     { $changed[] = 'title'; }
        if ($post_before->post_content !== $post_after->post_content) { $changed[] = 'content'; }
        if ($post_before->post_name !== $post_after->post_name)       { $changed[] = 'slug'; }
        if (empty($changed)) {
            return; // nothing user-visible changed
        }

        $this->log_action('post_updated', [
            'post_id'     => $post_id,
            'post_title'  => $post_after->post_title,
            'post_type'   => $post_after->post_type,
            'post_author' => $post_after->post_author,
            'changed'     => implode(', ', $changed),
        ]);
    }

    /** Elementor page/design saves (content lives in postmeta, not post_content). */
    public function log_elementor_save($document, $data = []) {
        if (!is_object($document) || !method_exists($document, 'get_post')) {
            return;
        }
        $post = $document->get_post();
        if (!$post instanceof WP_Post) {
            return;
        }
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) {
            return;
        }
        $this->log_action('post_updated', [
            'post_id'     => $post->ID,
            'post_title'  => $post->post_title,
            'post_type'   => $post->post_type,
            'post_author' => $post->post_author,
            'changed'     => 'Elementor design',
        ]);
    }

    /** Permanent deletion (fires on before_delete_post). */
    public function log_post_deleted($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        $ignore_types = ['revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'attachment'];
        if (in_array($post->post_type, $ignore_types, true)) {
            return;
        }
        // Skip auto-draft/placeholder rows that WordPress garbage-collects via
        // cron — those aren't real user deletions.
        if (in_array($post->post_status, ['auto-draft', 'inherit'], true)) {
            return;
        }
        $this->log_action('post_deleted', [
            'post_id'     => $post_id,
            'post_title'  => $post->post_title,
            'post_type'   => $post->post_type,
            'post_author' => $post->post_author,
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

    public function log_plugin_deleted($plugin_file, $deleted = true) {
        if (!$deleted) {
            return; // deletion attempt failed
        }
        $this->log_action('plugin_deleted', [
            'plugin' => $plugin_file,
        ]);
    }

    // Theme Actions
    public function log_theme_switched($new_name, $new_theme) {
        $this->log_action('theme_switched', [
            'new_theme' => $new_name,
            'theme_object' => is_object($new_theme) ? $new_theme->get_stylesheet() : '',
        ]);
    }

    /**
     * Core / plugin / theme installs & updates (fires on upgrader_process_complete).
     * $hook_extra describes what was upgraded and how.
     */
    public function log_upgrade_completed($upgrader, $hook_extra) {
        if (!is_array($hook_extra) || empty($hook_extra['type'])) {
            return;
        }

        $type   = $hook_extra['type'];   // plugin | theme | core
        $action = $hook_extra['action'] ?? 'update';  // install | update

        if ($type === 'core') {
            $this->log_action('core_updated', [
                'action' => $action,
            ]);
            return;
        }

        // Collect the affected items for plugin/theme (bulk or single).
        $items = [];
        if (!empty($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
            $items = $hook_extra['plugins'];
        } elseif (!empty($hook_extra['plugin'])) {
            $items = [$hook_extra['plugin']];
        } elseif (!empty($hook_extra['themes']) && is_array($hook_extra['themes'])) {
            $items = $hook_extra['themes'];
        } elseif (!empty($hook_extra['theme'])) {
            $items = [$hook_extra['theme']];
        }

        $event = ($type === 'theme') ? 'theme_updated' : 'plugin_updated';
        $this->log_action($event, [
            'action' => $action,
            'items'  => implode(', ', array_map('strval', $items)),
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

    // Option Changes — only security-relevant options (see is_loggable_option).
    public function log_option_updated($option_name, $old_value, $value) {
        if (!$this->is_loggable_option($option_name)) {
            return;
        }
        if ($old_value === $value) {
            return;
        }

        $this->log_action('option_updated', [
            'option_name' => $option_name,
            'old_value'   => $this->summarize_value($old_value),
            'new_value'   => $this->summarize_value($value),
        ]);
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