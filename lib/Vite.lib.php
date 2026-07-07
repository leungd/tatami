<?php

namespace Tatami;

class Vite {

    /**
     * Flag to determine whether hot server is active.
     *
     * @var bool
     */
    private static bool $isHot = false;

    /**
     * The URI to the hot server.
     *
     * @var string
     */
    private static string $server;

    /**
     * The path where compiled assets will go.
     *
     * @var string
     */
    private static string $buildPath = 'build';

    /**
     * Manifest file contents.
     *
     * @var array
     */
    private static array $manifest = [];

    /**
     * Check for the presence of a hot file and load the manifest.
     *
     * @return string|null The Vite client URL when running hot, null otherwise.
     */
    public static function init(): string|null
    {
        // The hot file only means anything in a dev environment — one that
        // reaches staging/production (accidental deploy, crashed dev server
        // on a shared host) must never put the site in hot mode. Within
        // local/development, a stale hot file still needs manual deletion.
        $isDevEnvironment = in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
        static::$isHot    = $isDevEnvironment && file_exists( static::hotFilePath() );

        if (static::$isHot) {
            static::$server = file_get_contents(static::hotFilePath());
            return static::$server . '/@vite/client';
        }

        // Fail soft without a manifest: an unstyled page beats a fatal on
        // every front-end request.
        if (!file_exists($manifestPath = static::buildPath() . '/.vite/manifest.json')) {
            error_log('Tatami: Vite manifest not found — run `pnpm build`, or start `pnpm dev` in a local environment.');
            return null;
        }

        $decoded = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($decoded)) {
            error_log('Tatami: Vite manifest is unreadable or corrupt — re-run `pnpm build`.');
            $decoded = [];
        }

        // store our manifest contents.
        static::$manifest = $decoded;

        return null;
    }

    /**
     * Whether assets can be resolved (dev server running or manifest loaded).
     *
     * @return bool
     */
    public static function ready(): bool
    {
        return static::$isHot || static::$manifest !== [];
    }

    /**
     * Admin notice when no manifest exists and no dev server is running.
     * Hooked directly to admin_notices (see Assets) — enqueue hooks never
     * fire in admin requests, so the check must run independently there.
     *
     * @return void
     */
    public static function maybe_render_missing_manifest_notice(): void
    {
        $isDevEnvironment = in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
        if ( $isDevEnvironment && file_exists( static::hotFilePath() ) ) {
            return;
        }

        if ( file_exists( static::buildPath() . '/.vite/manifest.json' ) ) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            sprintf(
                /* translators: %s: the build command */
                esc_html__( 'Tatami: no Vite manifest found, so no theme assets are enqueued. Run %s.', 'tatami' ),
                '<code>pnpm build</code>'
            )
        );
    }

    /**
     * Enqueue the Vite client module (dev server only).
     *
     * @return void
     */
    public static function enqueue_module(): void
    {
        // we only want to continue if we have a client.
        if (!$client = static::init()) {
            return;
        }

        // enqueue our client script
        wp_enqueue_script('vite-client', $client, [], null);

        // update html script type to module
        static::script_type_module('vite-client');
    }

    /**
     * Return URI path to an asset.
     *
     * @param $asset
     *
     * @return string
     * @throws \Exception
     */
    public static function asset($asset): string
    {
        if (static::$isHot) {
            return static::$server . '/' . ltrim($asset, '/');
        }

        if (!array_key_exists($asset, static::$manifest)) {
            throw new \Exception('Unknown Vite build asset: ' . $asset);
        }

        return implode('/', [ get_stylesheet_directory_uri(), static::$buildPath, static::$manifest[$asset]['file'] ]);
    }

    /**
     * Return URI paths to CSS files associated with a JS entry.
     *
     * @param $asset
     *
     * @return array
     */
    public static function css($asset): array
    {
        if (static::$isHot) {
            return [];
        }

        if (!array_key_exists($asset, static::$manifest) || !isset(static::$manifest[$asset]['css'])) {
            return [];
        }

        return array_map(function($cssFile) {
            return implode('/', [ get_stylesheet_directory_uri(), static::$buildPath, $cssFile ]);
        }, static::$manifest[$asset]['css']);
    }

    /**
     * Internal method to determine hotFilePath.
     *
     * @return string
     */
    private static function hotFilePath(): string
    {
        return implode('/', [static::buildPath(), 'hot']);
    }

    /**
     * Internal method to determine buildPath.
     *
     * @return string
     */
    private static function buildPath(): string
    {
        return implode('/', [get_stylesheet_directory(), static::$buildPath]);
    }

    /**
     * Update html script type to module.
     *
     * Mutates the tag WordPress built rather than replacing it, so attributes
     * added by core or plugins (CSP nonces, integrity) survive.
     *
     * @param string $scriptHandle
     * @return void
     */
    public static function script_type_module(string $scriptHandle): void
    {
        add_filter('script_loader_tag', function ($tag, $handle) use ($scriptHandle) {
            if ($scriptHandle !== $handle) {
                return $tag;
            }

            if (preg_match('/type=(["\'])[^"\']*\1/', $tag)) {
                return preg_replace('/type=(["\'])[^"\']*\1/', 'type="module"', $tag, 1);
            }

            return preg_replace('/<script /', '<script type="module" ', $tag, 1);
        }, 10, 2);
    }

}
