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

// A Content page's <h1> is the keyword line written in its body; a page
// whose body has none (a Listing page) promotes its title instead.
$context['hero_title_tag'] = str_contains( $post->post_content, '<h1' ) ? 'p' : 'h1';

$context['featured_image'] = Tatami\Queries::featured_image_with_fallback( $post );

Timber::render( array( 'page-' . $post->post_name . '.twig', 'page.twig' ), $context );
