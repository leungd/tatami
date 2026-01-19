<?php
/**
 * The template for displaying all pages.
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since    Timber 0.1
 */

$post             = Timber::get_post();
$context          = Timber::context();
$context['post']  = $post;
$context['slug']  = 'page-' . $post->post_name;
$context['title'] = $post->post_title;

$context['site']->setup_featured_image($post, $context);

$template = null;

switch ($post->post_name) {
}

renderPageTemplate($post, $context, $template);

function renderPageTemplate($post, array &$context, ?string $template) {
    if (!empty($template)) {
        Timber::render(['page-' . $template . '.twig', 'page.twig'], $context);
    } else {
        Timber::render(['page-' . $post->post_name . '.twig', 'page.twig'], $context);
    }
}
