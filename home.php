<?php
/**
 * The posts-page (blog index) template.
 *
 * The posts page assigned under Settings → Reading routes here — never
 * through page.php — so page-{slug}.twig resolution has to happen here
 * for the theme's page-template convention to hold.
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

$context = Timber::context();

$blog_page = get_option( 'page_for_posts' ) ? Timber::get_post( (int) get_option( 'page_for_posts' ) ) : null;

$templates = array( 'home.twig', 'index.twig' );
if ( $blog_page ) {
    array_unshift( $templates, 'page-' . $blog_page->post_name . '.twig' );
    // The Blog page itself — its title/ACF fields drive the header.
    $context['post'] = $blog_page;
}
$context['title'] = $blog_page ? $blog_page->title() : __( 'Blog', 'tatami' );

Timber::render( $templates, $context );
