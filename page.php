<?php
/**
 * The template for displaying all pages.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

// Timber::context() re-registers the global $post as a WP_Post while
// setting up the loop, so the Timber post must be read from the context
// after that call, never captured before it.
$context = Timber::context();
$post    = $context['post'];

$context['title'] = $post->title();

$context['featured_image'] = Tatami\Queries::featured_image_with_fallback( $post );

Timber::render( array( 'page-' . $post->post_name . '.twig', 'page.twig' ), $context );
