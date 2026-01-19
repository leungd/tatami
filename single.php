<?php
/**
 * The Template for displaying all single posts
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since    Timber 0.1
 */

$post               = Timber::get_post();
$context            = Timber::context();
$context['post']    = $post;
$context['title']   = get_the_title();

$context['site']->setup_featured_image($post, $context);

switch ($post->post_type) {
    case 'post':
        setupBlogPostType($post, $context);
        break;
}

renderTemplate($post, $context);

function setupBlogPostType($post, &$context) {
    $context['tag'] = 'article';
}

function renderTemplate($post, &$context) {
    if (post_password_required($post->ID)) {
        Timber::render('single-password.twig', $context);
    } else {
        Timber::render(array(
            'single-' . $post->ID . '.twig',
            'single-' . $post->post_type . '.twig',
            'single-' . $post->slug . '.twig',
            'single.twig'
        ), $context);
    }
}
