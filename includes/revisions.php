<?php
/**
 * TFM Revision Cleanup — AJAX handlers for the revision tool
 * Moved verbatim from topfiremedia.php during modularization — no logic change.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Revision cleanup AJAX handlers
function tfm_get_revision_stats() {
    check_ajax_referer('tfm_revision_cleanup', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    global $wpdb;

    // Get total revision count
    $total_revisions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");

    // Get posts with revisions
    $posts_with_revisions = $wpdb->get_var("
        SELECT COUNT(DISTINCT p.post_parent)
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'revision'
        AND p.post_parent > 0
    ");

    // Estimate database size (rough calculation: ~1KB per revision)
    $estimated_size = round($total_revisions * 0.001, 2);

    wp_send_json_success([
        'total_revisions' => (int) $total_revisions,
        'posts_with_revisions' => (int) $posts_with_revisions,
        'estimated_size' => $estimated_size
    ]);
}

function tfm_cleanup_revisions() {
    check_ajax_referer('tfm_revision_cleanup', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    $cleanup_type = sanitize_text_field($_POST['cleanup_type']);
    $days = isset($_POST['days']) ? (int) $_POST['days'] : 30;

    global $wpdb;
    $deleted_count = 0;

    switch ($cleanup_type) {
        case 'excess':
            // Get the revision limit from settings
            $settings = get_option('tfm_plugin_settings', []);
            $limit = isset($settings['wp_post_revisions_limit']) ? (int) $settings['wp_post_revisions_limit'] : 5;

            if ($limit > 0) {
                // For each post, keep only the most recent N revisions
                $posts_with_revisions = $wpdb->get_results("
                    SELECT post_parent, COUNT(*) as revision_count
                    FROM {$wpdb->posts}
                    WHERE post_type = 'revision' AND post_parent > 0
                    GROUP BY post_parent
                    HAVING revision_count > {$limit}
                ");

                foreach ($posts_with_revisions as $post) {
                    // Delete excess revisions, keeping only the most recent ones
                    $deleted = $wpdb->query($wpdb->prepare("
                        DELETE r1 FROM {$wpdb->posts} r1
                        INNER JOIN {$wpdb->posts} r2 ON r1.post_parent = r2.post_parent
                        AND r1.post_type = 'revision' AND r2.post_type = 'revision'
                        AND r1.post_parent = %d
                        WHERE r1.post_date < r2.post_date
                        ORDER BY r1.post_date DESC
                        LIMIT %d
                    ", $post->post_parent, $post->revision_count - $limit));

                    $deleted_count += $deleted;
                }
            }
            break;

        case 'old':
            // Delete revisions older than X days
            $deleted_count = $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->posts}
                WHERE post_type = 'revision'
                AND post_date < DATE_SUB(NOW(), INTERVAL %d DAY)
            ", $days));
            break;

        case 'all':
            // Delete ALL revisions (destructive!)
            $deleted_count = $wpdb->delete($wpdb->posts, ['post_type' => 'revision']);
            break;
    }

    wp_send_json_success([
        'deleted_count' => (int) $deleted_count,
        'message' => "Successfully deleted {$deleted_count} revisions"
    ]);
}

add_action('wp_ajax_tfm_get_revision_stats', 'tfm_get_revision_stats');
add_action('wp_ajax_tfm_cleanup_revisions', 'tfm_cleanup_revisions');
