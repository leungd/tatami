<?php
/**
 * The main template file — the generic fallback when nothing more
 * specific in the template hierarchy matches a query.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$context                = Timber::context();
$context['title']       = 'Blog';
$templates              = array( 'index.twig' );
Timber::render( $templates, $context );
