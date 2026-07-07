<?php
/**
 * The front page template
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$context = Timber::context();

Timber::render( 'front-page.twig', $context );
