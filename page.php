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
$context['slug']  = 'page-' . $post->post_name;
$context['title'] = $post->post_title;

$context['site']->setup_featured_image($post, $context);

Timber::render( array( 'page-' . $post->post_name . '.twig', 'page.twig' ), $context );
