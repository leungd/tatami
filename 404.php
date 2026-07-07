<?php
/**
 * The template for displaying 404 pages (Not Found)
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$context = Timber::context();
$context['title'] = __( 'Oops! Page not found.', 'tatami' );
Timber::render( '404.twig', $context );
