<?php

namespace Tatami;

use Timber\Site as TimberSite;
use Timber\Timber;

class Site extends TimberSite {
    public function __construct() {
        add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
        add_action( 'template_redirect', array( $this, 'disable_author_archives' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'hide_acf_grouped_labels' ) );
        add_action( 'admin_menu', array( $this, 'remove_comments_admin_menu' ) );

        add_filter( 'upload_mimes', array( $this, 'add_svg_mime_type' ) );
        add_filter( 'timber/context', array( $this, 'add_to_context' ) );
        add_filter( 'timber/twig', array( $this, 'add_to_twig' ) );
        add_filter( 'timber/twig/environment/options', array( $this, 'set_twig_environment_options' ) );

        // Timber substitutes the password form for protected posts' content.
        add_filter( 'timber/post/content/show_password_form_for_protected', '__return_true' );

        // Comments are disabled site-wide per house policy. A site that
        // genuinely needs them deletes these three filters and the
        // remove_comments_admin_menu hook above.
        add_filter( 'comments_open', '__return_false', 20 );
        add_filter( 'pings_open', '__return_false', 20 );
        add_filter( 'comments_array', '__return_empty_array', 20 );

        // XML-RPC pingbacks are a spam/DDoS vector no client site uses.
        add_filter( 'xmlrpc_methods', array( $this, 'disable_xmlrpc_pingbacks' ) );

        parent::__construct();
    }

    /**
     * This is where you can register custom post types.
     */
    public function register_post_types() {

    }

    /**
     * This is where you can register custom taxonomies.
     */
    public function register_taxonomies() {

    }

    /**
     * Hide ACF group labels when the group is contained within a tab —
     * the tab already provides the heading.
     */
    public function hide_acf_grouped_labels(): void {
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            return;
        }

        wp_add_inline_style( 'acf-input', '
            .acf-fields > .acf-field-tab ~ .acf-field-group > .acf-label {
                display: none !important;
            }
        ' );
    }

    /**
     * Twig autoescaping is the theme's escaping contract: {{ var }} escapes
     * for HTML; trusted HTML (post.content, post.excerpt) opts in with | raw.
     */
    public function set_twig_environment_options( $options ) {
        $options['autoescape'] = 'html';
        return $options;
    }

    /**
     * Author archives are unused on client sites but publicly reachable
     * (/?author=N resolves on any WP site) and enable username enumeration —
     * 404 them. A site that needs them removes this and uses Timber::get_user().
     */
    public function disable_author_archives(): void {
        if ( ! is_author() ) {
            return;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }

    public function disable_xmlrpc_pingbacks( $methods ) {
        unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
        return $methods;
    }

    public function remove_comments_admin_menu(): void {
        remove_menu_page( 'edit-comments.php' );
    }

    /**
     * Add SVG to allowed mime types — administrators only. SVG can carry
     * scripts (XSS vector); client sites should pair this with a sanitizer
     * plugin such as Safe SVG.
     *
     * @param array $mimes Mime types keyed by the file extension regex corresponding to those types.
     * @return array Modified mime types
     */
    public function add_svg_mime_type($mimes) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $mimes;
        }
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * This is where you add some context
     *
     * @param string $context context['this'] Being the Twig's {{ this }}.
     */
    public function add_to_context( $context ) {
        $context['menu']  = Timber::get_menu('primary');
        $context['site']  = $this;

        if (function_exists('get_fields')) {
            // Loads every options-page field on every request (including 404s).
            // Cheap while options pages stay lean — bloat here has global cost.
            $context['options'] = get_fields('option');
        }

        return $context;
    }

    public function theme_supports() {
        add_theme_support( 'title-tag' );
        add_theme_support( 'post-thumbnails' );

        add_theme_support(
            'html5',
            array(
                'gallery',
                'caption',
                'script',
                'style',
            )
        );

        register_nav_menus([
            'primary' => __('Primary Menu', 'tatami'),
        ]);
    }

    /**
     * This is where you can add your own functions to twig.
     *
     * @param Twig\Environment $twig get extension.
     */
    public function add_to_twig( $twig ) {
        // Add custom Twig filters here

        return $twig;
    }
}
