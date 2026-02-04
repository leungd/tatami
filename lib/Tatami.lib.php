<?php

use Timber\Site;

class TatamiTheme extends Site {
	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		add_filter( 'upload_mimes', array( $this, 'add_svg_mime_type' ) );

		add_filter( 'timber/context', array( $this, 'add_to_context' ) );

		// Hide ACF Group Labels if contained within a tab
		add_action('admin_enqueue_scripts', function () {
			if (function_exists('acf_get_field_groups')) {
				wp_add_inline_style('acf-input', '
					.acf-fields > .acf-field-tab ~ .acf-field-group > .acf-label {
						display: none !important;
					}
				');
			}
		});

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
	 * Add SVG to allowed mime types
	 *
	 * @param array $mimes Mime types keyed by the file extension regex corresponding to those types.
	 * @return array Modified mime types
	 */
	public function add_svg_mime_type($mimes) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Setup Featured Image with Parent Fallback
	 *
	 * @param Timber\Post $post
	 * @param array $context
	 * @return void
	 */
	public function setup_featured_image($post, &$context) {
		$featured_image = $post->thumbnail();

		// Only look for parent featured image if current post doesn't have one
		if (!$featured_image && $post->post_parent) {
			$parent_post = Timber::get_post($post->post_parent);
			$parent_featured_image = $parent_post->thumbnail();

			if ($parent_featured_image) {
				$featured_image = $parent_featured_image;
			}
		}

		if ($featured_image) {
			$context['featured_image'] = true;
			$context['featured_image_src'] = $featured_image->src();
			$context['featured_image_alt'] = $featured_image->alt();
		}
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
			$context['options'] = get_fields('option');
		}

		return $context;
	}

	public function theme_supports() {
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );

		add_theme_support(
			'html5',
			array(
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
			)
		);

		add_theme_support(
			'post-formats',
			array(
				'aside',
				'image',
				'video',
				'quote',
				'link',
				'gallery',
				'audio',
			)
		);

		add_theme_support( 'menus' );

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
