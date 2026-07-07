<?php

namespace Tatami;

class Assets {

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_styles_scripts' ], 20);
        add_action('admin_notices', [ Vite::class, 'maybe_render_missing_manifest_notice' ]);
    }

    /**
     * Enqueue theme styles and scripts.
     *
     * Scripts are enqueued in <head> because ES modules are deferred by default,
     * so there is no render-blocking penalty.
     *
     * @return void
     */
    public function enqueue_styles_scripts(): void
    {
        // enqueue the Vite client when the dev server is running
        // (this also loads the manifest for production builds)
        Vite::enqueue_module();

        // no dev server and no build output — Vite::init() already logged it
        if (!Vite::ready()) {
            return;
        }

        // enqueue CSS bundled with the JS entry
        $cssFiles = Vite::css('src/js/main.js');
        foreach ($cssFiles as $index => $cssFile) {
            wp_enqueue_style('theme-style-' . $index, $cssFile, [], null, 'all');
        }

        // register and enqueue the main JS entry
        $filename = Vite::asset('src/js/main.js');
        wp_enqueue_script('theme-script', $filename, [], null, false);

        // update html script type to module
        Vite::script_type_module('theme-script');
    }

}
