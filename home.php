<?php
/**
 * The home/blog index template
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$context          = Timber::context();
$context['posts'] = Timber::get_posts();
$templates        = array( 'home.twig' );
Timber::render( $templates, $context );
