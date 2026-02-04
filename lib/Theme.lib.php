<?php

class Theme {

	public function __construct()
	{
		add_action('wp_enqueue_scripts', [ $this, 'enqueue_styles_scripts' ], 20);
	}

	/**
	 * Enqueue theme styles and scripts.
	 *
	 * Scripts are enqueued in <head> because ES modules are deferred by default,
	 * so there is no render-blocking penalty.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function enqueue_styles_scripts(): void
	{
		// enqueue the Vite module
		Vite::enqueue_module();

		// enqueue CSS bundled with the JS entry
		$cssFiles = Vite::css('src/js/main.js');
		foreach ($cssFiles as $index => $cssFile) {
			wp_enqueue_style('theme-style-' . $index, $cssFile, [], null, 'screen');
		}

		// register and enqueue the main JS entry
		$filename = Vite::asset('src/js/main.js');
		wp_enqueue_script('theme-script', $filename, [], null, false);

		// update html script type to module
		Vite::script_type_module('theme-script');
	}

}
