<?php
/**
 * The Template for displaying all single posts
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$post             = Timber::get_post();
$context          = Timber::context();
$context['post']  = $post;
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
