<?php
/**
 * The Template for displaying all single posts
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

// Timber::context() re-registers the global $post as a WP_Post while
// setting up the loop, so the Timber post must be read from the context
// after that call, never captured before it.
$context = Timber::context();
$post    = $context['post'];

$context['title'] = get_the_title();

$context['featured_image'] = Tatami\Queries::featured_image_with_fallback( $post );

if ($post->post_type === 'post') {
    $context['tag'] = 'article';
}

Timber::render(array(
    'single-' . $post->slug . '.twig',
    'single-' . $post->post_type . '.twig',
    'single.twig'
), $context);
