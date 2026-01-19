<?php
/**
 * Search results page
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.1
 */

$templates = array( 'search.twig', 'archive.twig', 'index.twig' );

$context          = Timber::context();
$context['title'] = 'Search results for ' . get_search_query();
$context['search_query'] = get_search_query();

// Get Featured Image from  Page ID 75
$context['featured_image'] = Timber::get_post(75)->thumbnail();
$context['featured_image_src'] = $context['featured_image']->src();
$context['featured_image_alt'] = $context['featured_image']->alt();
$context['featured_image_caption'] = $context['featured_image']->caption();
$context['featured_image_description'] = $context['featured_image']->description();
$context['featured_image_title'] = $context['featured_image']->title();
$context['featured_image_url'] = $context['featured_image']->url();

// Check if searching for specific post type
$post_type = isset($_GET['post_type']) ? $_GET['post_type'] : '';
if ($post_type === 'article') {
	$context['title'] = 'Article search results for "' . get_search_query() . '"';
	$context['is_article_search'] = true;
}

$context['posts'] = Timber::get_posts();

Timber::render( $templates, $context );
