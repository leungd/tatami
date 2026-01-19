<?php

use Timber\Site;

class TatamiTheme extends Site {
	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		add_filter( 'upload_mimes', array( $this, 'add_svg_mime_type' ) );

		add_filter( 'timber/context', array( $this, 'add_to_context' ) );
		add_filter( 'timber/twig', array( $this, 'add_to_twig' ) );
		add_filter( 'timber/twig/environment/options', [ $this, 'update_twig_environment_options' ] );

        // Hide ACF Group Labels if contained within a tab
        add_action('admin_head', function () {
            echo '<style>
                .acf-fields > .acf-field-tab ~ .acf-field-group > .acf-label {
                    display: none !important;
                }
            </style>';
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
		$context['menu']  = Timber::get_menu();
		$context['site']  = $this;
		$context['options'] = get_fields('option');

		// Set the default tag for the main content area
		$context['tag'] = 'div';

		// Setup featured image if there's a post in the context
		if (isset($context['post'])) {
			$this->setup_featured_image($context['post'], $context);
		}

		return $context;
	}

	public function theme_supports() {
		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		/*
		 * Let WordPress manage the document title.
		 * By adding theme support, we declare that this theme does not use a
		 * hard-coded <title> tag in the document head, and expect WordPress to
		 * provide it for us.
		 */
		add_theme_support( 'title-tag' );

		/*
		 * Enable support for Post Thumbnails on posts and pages.
		 *
		 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		 */
		add_theme_support( 'post-thumbnails' );

		/*
		 * Switch default core markup for search form, comment form, and comments
		 * to output valid HTML5.
		 */
		add_theme_support(
			'html5',
			array(
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
			)
		);

		/*
		 * Enable support for Post Formats.
		 *
		 * See: https://codex.wordpress.org/Post_Formats
		 */
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
	}


	/**
	 * This is where you can add your own functions to twig.
	 *
	 * @param Twig\Environment $twig get extension.
	 */
	public function add_to_twig( $twig ) {
		/**
		 * Required when you want to use Twig's template_from_string.
		 * @link https://twig.symfony.com/doc/3.x/functions/template_from_string.html
		 */
		// $twig->addExtension( new Twig\Extension\StringLoaderExtension() );

		// Add custom Twig filters here

		return $twig;
	}

	/**
	 * Updates Twig environment options.
	 *
	 * @link https://twig.symfony.com/doc/2.x/api.html#environment-options
	 *
	 * \@param array $options An array of environment options.
	 *
	 * @return array
	 */
	function update_twig_environment_options( $options ) {
	    // $options['autoescape'] = true;

	    return $options;
	}
}
