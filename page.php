<?php
/**
 * The template for displaying all pages.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$post             = Timber::get_post();
$context          = Timber::context();
$context['post']  = $post;
$context['title'] = $post->title();

$context['featured_image'] = Tatami\Queries::featured_image_with_fallback( $post );

Timber::render( array( 'page-' . $post->post_name . '.twig', 'page.twig' ), $context );
