<?php
/**
 * Search results page
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$templates = array( 'search.twig', 'archive.twig', 'index.twig' );

$context          = Timber::context();
/* translators: %s: search query */
$context['title'] = sprintf( __( 'Search results for %s', 'tatami' ), get_search_query( false ) );
$context['search_query'] = get_search_query( false );

Timber::render( $templates, $context );
