<?php
/**
 * The template for displaying Archive pages.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$templates = array( 'archive.twig', 'index.twig' );

$context = Timber::context();

$context['title'] = __( 'Archive', 'tatami' );
if ( is_day() ) {
    /* translators: %s: date */
    $context['title'] = sprintf( __( 'Archive: %s', 'tatami' ), get_the_date( 'D M Y' ) );
} elseif ( is_month() ) {
    /* translators: %s: month */
    $context['title'] = sprintf( __( 'Archive: %s', 'tatami' ), get_the_date( 'M Y' ) );
} elseif ( is_year() ) {
    /* translators: %s: year */
    $context['title'] = sprintf( __( 'Archive: %s', 'tatami' ), get_the_date( 'Y' ) );
} elseif ( is_tag() ) {
    $context['title'] = single_tag_title( '', false );
} elseif ( is_category() ) {
    /* translators: %s: category name */
    $context['title'] = sprintf( __( 'Category: %s', 'tatami' ), single_cat_title( '', false ) );
    array_unshift( $templates, 'archive-category-' . get_queried_object()->slug . '.twig' );
} elseif ( is_post_type_archive() ) {
    $context['title'] = post_type_archive_title( '', false );
    array_unshift( $templates, 'archive-' . get_post_type() . '.twig' );
}

Timber::render( $templates, $context );
