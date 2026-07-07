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

Timber::render( $templates, $context );
