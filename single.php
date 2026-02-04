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

$context['site']->setup_featured_image($post, $context);

if ($post->post_type === 'post') {
	$context['tag'] = 'article';
}

if (post_password_required($post->ID)) {
	Timber::render('single-password.twig', $context);
} else {
	Timber::render(array(
		'single-' . $post->post_type . '.twig',
		'single-' . $post->slug . '.twig',
		'single.twig'
	), $context);
}
