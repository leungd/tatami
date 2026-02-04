<?php
/**
 * The front page template
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$context          = Timber::context();
$context['posts'] = Timber::get_posts();
$context['post']  = Timber::get_post();

Timber::render( 'front-page.twig', $context );
