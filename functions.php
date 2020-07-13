<?php
/**
 * Timber starter-theme
 * https://github.com/timber/starter-theme
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.1
 */

/**
 * If you are installing Timber as a Composer dependency in your theme, you'll need this block
 * to load your dependencies and initialize Timber. If you are using Timber via the WordPress.org
 * plug-in, you can safely delete this block.
 */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
	$timber = new Timber\Timber();
}

/**
 * This ensures that Timber is loaded and available as a PHP class.
 * If not, it gives an error message to help direct developers on where to activate
 */
if ( ! class_exists( 'Timber' ) ) {

	add_action(
		'admin_notices',
		function() {
			echo '<div class="error"><p>Timber not activated. Make sure you activate the plugin in <a href="' . esc_url( admin_url( 'plugins.php#timber' ) ) . '">' . esc_url( admin_url( 'plugins.php' ) ) . '</a></p></div>';
		}
	);

	add_filter(
		'template_include',
		function( $template ) {
			return get_stylesheet_directory() . '/static/no-timber.html';
		}
	);
	return;
}

/**
 * Sets the directories (inside your theme) to find .twig files
 */
Timber::$dirname = array( 'templates', 'views' );

/**
 * By default, Timber does NOT autoescape values. Want to enable Twig's autoescape?
 * No prob! Just set this value to true
 */
Timber::$autoescape = false;

/**
 * We're going to configure our theme inside of a subclass of Timber\Site
 * You can move this to its own file and include here via php's include("MySite.php")
 */
class Tatami extends Timber\Site {
	/** Add timber support. */
	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
		add_filter( 'timber/context', array( $this, 'add_to_context' ) );
		add_filter( 'timber/twig', array( $this, 'add_to_twig' ) );
        add_action( 'init', array( $this, 'deregister_scripts_and_styles' ) );
        add_action( 'init', array( $this, 'register_tatami_scripts_and_styles' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		parent::__construct();
    }

	/** Remove extra Wordpress styles and scripts. */
    public function deregister_scripts_and_styles() {
        // remove some meta tags from WordPress
        remove_action('wp_head', 'wp_generator');
        function remove_dns_prefetch( $hints, $relation_type ) {
            if ( 'dns-prefetch' === $relation_type ) {
                return array_diff( wp_dependencies_unique_hosts(), $hints );
            }

            return $hints;
        }
        remove_action ('wp_head', 'rsd_link');
        remove_action( 'wp_head', 'wlwmanifest_link');
        remove_action( 'wp_head', 'wp_shortlink_wp_head');

        //remove json api capabilities
        function remove_json_api () {

            // Remove the REST API lines from the HTML Header
            remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );

            // Remove the REST API endpoint.
            remove_action( 'rest_api_init', 'wp_oembed_register_route' );

            // Turn off oEmbed auto discovery.
            add_filter( 'embed_oembed_discover', '__return_false' );

            // Don't filter oEmbed results.
            remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );

            // Remove oEmbed discovery links.
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

            // Remove oEmbed-specific JavaScript from the front-end and back-end.
            remove_action( 'wp_head', 'wp_oembed_add_host_js' );

        }
        add_action( 'after_setup_theme', 'remove_json_api' );

        //completely disable json api
        function disable_json_api () {

        // Filters for WP-API version 1.x
        add_filter('json_enabled', '__return_false');
        add_filter('json_jsonp_enabled', '__return_false');

        // Filters for WP-API version 2.x
        add_filter('rest_enabled', '__return_false');
        add_filter('rest_jsonp_enabled', '__return_false');

        }
        add_action( 'after_setup_theme', 'disable_json_api' );

        // Remove auto generated feed links
        function my_remove_feeds() {
        remove_action( 'wp_head', 'feed_links_extra', 3 );
        remove_action( 'wp_head', 'feed_links', 2 );
        }
        add_action( 'after_setup_theme', 'my_remove_feeds' );

        add_filter( 'wp_resource_hints', 'remove_dns_prefetch', 10, 2 );

        //remove emoji scripts from head
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
    }

    public function register_tatami_scripts_and_styles() {
        /**
         * Return url of compiled style or script file
         */
        function assets($key) {
            $manifest_string = file_get_contents(get_template_directory() . '/static/manifest.json');
            $manifest_array  = json_decode($manifest_string, true);

            return get_stylesheet_directory_uri() . '/static/' . $manifest_array[$key];
        }

        // Register styles
        wp_register_style( 'tatami-styles', assets('app.css'), [], '', 'all' );

        // Register scripts
        wp_register_script( 'tatami-scripts', assets('app.js'), [], '', true );

        // Enqueue scripts and styles
        wp_enqueue_script( 'tatami-scripts' );
        wp_enqueue_style( 'tatami-styles' );

        /**
         * Return true if assets file exists
         */
        function has_assets($key) {
            $manifest_string = file_get_contents(get_template_directory() . '/static/manifest.json');
            $manifest_array  = json_decode($manifest_string, true);

            return @file_get_contents(get_template_directory() . '/static/' . $manifest_array[$key]);
        }

    }

	/** This is where you can register custom post types. */
	public function register_post_types() {

    }

	/** This is where you can register custom taxonomies. */
	public function register_taxonomies() {

	}

	/** This is where you add some context
	 *
	 * @param string $context context['this'] Being the Twig's {{ this }}.
	 */
	public function add_to_context( $context ) {
        $context['images'] = get_template_directory_uri() . '/resources/assets/images/';
		$context['foo']   = 'bar';
		$context['stuff'] = 'I am a value set in your functions.php file';
		$context['notes'] = 'These values are available everytime you call Timber::context();';
		$context['menu']  = new Timber\Menu();
		$context['site']  = $this;
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

	/** This Would return 'foo bar!'.
	 *
	 * @param string $text being 'foo', then returned 'foo bar!'.
	 */
	public function myfoo( $text ) {
		$text .= ' bar!';
		return $text;
	}

	/** This is where you can add your own functions to twig.
	 *
	 * @param string $twig get extension.
	 */
	public function add_to_twig( $twig ) {
		$twig->addExtension( new Twig\Extension\StringLoaderExtension() );
		$twig->addFilter( new Twig\TwigFilter( 'myfoo', array( $this, 'myfoo' ) ) );
		return $twig;
	}

}

new Tatami();
