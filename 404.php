<?php
/**
 * The template for displaying 404 pages (Not Found)
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$context = Timber::context();
$context['title'] = 'Oops! Page not found.';
Timber::render( '404.twig', $context );
