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
}
