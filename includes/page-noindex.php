<?php
/**
 * TFM Utility-Page Noindex
 *
 * Thank-you, coming-soon and leftover blank "new page" templates should never be
 * indexed: thank-you pages leak conversion funnels into search results, coming-soon
 * pages get indexed before a site launches and then rank for the brand, and blank
 * pages are pure thin content.
 *
 * Why this lives in the TFM plugin rather than Rank Math:
 * Rank Math is active on well under half the fleet, and several sites don't have it
 * installed at all. Turning an SEO plugin on just to set one flag would rewrite
 * titles, meta descriptions, sitemaps and schema across a live client site — a far
 * bigger change than the problem warrants. The TFM plugin is already on every site
 * and auto-updates, so this guarantees the behaviour everywhere, including sites
 * built later.
 *
 * Three robots surfaces are hooked because whichever SEO plugin is active owns the
 * output and the others never run:
 *   - wp_robots                  WordPress core (sites with no SEO plugin)
 *   - rank_math/frontend/robots  Rank Math
 *   - wpseo_robots_array         Yoast
 *
 * Matching is by slug, not title, so translated or re-worded page titles don't
 * silently opt a page out. Only singular pages are considered — never posts,
 * archives, or the front page, so a site whose homepage is somehow slugged
 * "coming-soon" can't be deindexed by accident.
 *
 * Opt a single page back in:
 *   add_filter( 'tfm_noindex_utility_page', function ( $noindex, $post_id ) {
 *       return get_post_field( 'post_name', $post_id ) === 'thank-you-keep-me'
 *           ? false : $noindex;
 *   }, 10, 2 );
 *
 * Change what counts as a utility page:
 *   add_filter( 'tfm_noindex_slug_patterns', function ( $patterns ) { ... } );
 *
 * Turn the whole thing off on one site:
 *   add_filter( 'tfm_noindex_utility_page', '__return_false' );
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Slug patterns that mark a page as a non-indexable utility page.
 *
 * Anchored so 'thank-you' and 'thank-you-download' match but 'thanksgiving-menu'
 * does not, and 'coming-soon' matches but 'up-and-coming-franchises' does not.
 *
 * @return array Regex patterns, matched against the page slug.
 */
function tfm_noindex_slug_patterns() {
    // Deliberately tight. An open-ended suffix wildcard would also swallow real
    // content pages — 'thank-you-note-blog-post-about-etiquette' and
    // 'coming-soon-blog-recap' both match a naive /^thank-you.*$/ — and wrongly
    // deindexing a page that earns traffic is far more costly than leaving one
    // thank-you page indexed. So: the base slug, optionally followed by a single
    // token from a known-safe list or a number. Anything wordier is reported for a
    // human to review rather than deindexed automatically.
    $base   = '(thank[-_]?you|coming[-_]?soon|new[-_]?page|blank)';
    $suffix = '([-_](page|copy|download|new|\d+)|[-_]?\d+)?';

    $patterns = array(
        '/^' . $base . $suffix . '$/i',        // thank-you, thank-you-download, new-page-2
        '/^[a-z0-9]+[-_]thank[-_]?you$/i',     // fb-thank-you, ppc-thank-you
    );

    /**
     * Filter the slug patterns treated as utility pages.
     *
     * @param array $patterns Regex patterns.
     */
    return apply_filters('tfm_noindex_slug_patterns', $patterns);
}

/**
 * Should the page currently being rendered be noindexed?
 *
 * Cached per request — each of the three robots filters may fire, and on some
 * themes more than once.
 *
 * @return bool
 */
function tfm_is_noindex_utility_page() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = false;

    // Singular pages only. is_front_page() is excluded so a homepage can never be
    // deindexed by its slug.
    if (!is_singular('page') || is_front_page()) {
        return $cache;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return $cache;
    }

    $slug    = (string) get_post_field('post_name', $post_id);
    $noindex = false;

    if ($slug !== '') {
        foreach (tfm_noindex_slug_patterns() as $pattern) {
            if (preg_match($pattern, $slug)) {
                $noindex = true;
                break;
            }
        }
    }

    /**
     * Filter whether this page should be noindexed.
     *
     * @param bool $noindex Whether to noindex.
     * @param int  $post_id Page ID.
     * @param string $slug  Page slug.
     */
    $cache = (bool) apply_filters('tfm_noindex_utility_page', $noindex, $post_id, $slug);

    return $cache;
}

/**
 * WordPress core robots output (sites with no SEO plugin active).
 *
 * wp_robots_no_robots() would also strip max-image-preview, so set the directives
 * directly and drop the ones that contradict noindex.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function tfm_noindex_wp_robots($robots) {
    if (!tfm_is_noindex_utility_page()) {
        return $robots;
    }
    $robots['noindex']  = true;
    $robots['nofollow'] = true;
    unset($robots['index'], $robots['follow'], $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview']);
    return $robots;
}
add_filter('wp_robots', 'tfm_noindex_wp_robots', 20);

/**
 * Rank Math robots output. Its array is keyed by directive with string values.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function tfm_noindex_rank_math_robots($robots) {
    if (!tfm_is_noindex_utility_page()) {
        return $robots;
    }
    if (!is_array($robots)) {
        $robots = array();
    }
    $robots['index']  = 'noindex';
    $robots['follow'] = 'nofollow';
    unset($robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview']);
    return $robots;
}
add_filter('rank_math/frontend/robots', 'tfm_noindex_rank_math_robots', 20);

/**
 * Yoast robots output. Same shape as Rank Math's.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function tfm_noindex_yoast_robots($robots) {
    if (!tfm_is_noindex_utility_page()) {
        return $robots;
    }
    if (!is_array($robots)) {
        $robots = array();
    }
    $robots['index']  = 'noindex';
    $robots['follow'] = 'nofollow';
    return $robots;
}
add_filter('wpseo_robots_array', 'tfm_noindex_yoast_robots', 20);
