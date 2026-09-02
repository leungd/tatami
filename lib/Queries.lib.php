<?php
/**
 * Reusable Timber queries for the Tatami theme.
 *
 * Keeps post-fetching logic in one place so routers (front-page.php,
 * page.php, single.php, …) stay thin and the Site class stays about setup.
 * One static method per logical query, named for intent, with parameters
 * for the variations callers need.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

namespace Tatami;

use Timber\Image;
use Timber\Post;
use Timber\Timber;

class Queries {

    /**
     * Featured image with parent fallback.
     *
     * Pages deep in a section often have no thumbnail of their own — the
     * section parent's image is the intended hero. Returns a Timber image
     * (templates read featured_image.src / featured_image.alt) or null when
     * neither the post nor its parent has one.
     */
    public static function featured_image_with_fallback( Post $post ): ?Image {
        $image = $post->thumbnail();

        if ( ! $image && $post->post_parent ) {
            $parent = Timber::get_post( $post->post_parent );
            $image  = $parent ? $parent->thumbnail() : null;
        }

        return $image ?: null;
    }

    /**
     * Related Posts for a host page — a Service, a Lawyer, any post type
     * carrying the `related_categories` ACF field (see AGENTS.md "Related
     * Posts (house tool)"). Newest published posts in any category the host
     * subscribes to. Returns an empty array when the host subscribes to
     * nothing: WP treats an empty category__in as "no filter" and would
     * otherwise return every post.
     */
    public static function related_posts( Post $host, int $count = 3 ): iterable {
        // Without ACF the field is raw postmeta, not the ID array ACF returns.
        $category_ids = $host->meta( 'related_categories' );
        if ( ! is_array( $category_ids ) ) {
            return array();
        }

        $category_ids = array_filter( array_map( 'intval', $category_ids ) );
        if ( ! $category_ids ) {
            return array();
        }

        return Timber::get_posts( array(
            'post_type'      => 'post',
            'posts_per_page' => $count,
            'category__in'   => $category_ids,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ) );
    }
}
