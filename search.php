<?php
/**
 * Search results page
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$templates = array( 'search.twig', 'archive.twig', 'index.twig' );

$context          = Timber::context();
$context['title'] = 'Search results for ' . get_search_query();
$context['search_query'] = get_search_query();

$post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
if ($post_type) {
	$context['post_type'] = $post_type;
}

$context['posts'] = Timber::get_posts();

Timber::render( $templates, $context );
