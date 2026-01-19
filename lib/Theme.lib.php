<?php

class Theme {

    public function __construct()
    {

        // enqueue admin styles scripts
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_styles_scripts' ], 20);

    }

    /**
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

        // register theme-script-js
        $filename = Vite::asset('src/js/main.js');

        // enqueue theme-script-js into our head (change false to true for footer)
        wp_enqueue_script('theme-script', $filename, [], null, false);

        // update html script type to module wp hack
        Vite::script_type_module('theme-script');

    }

}

new Theme();