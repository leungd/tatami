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
        // The hot file only means anything on a dev machine. A stale one —
        // crashed dev server, or one that reached a server — must never put
        // the site in hot mode, so hot detection is gated on environment.
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
            add_action('admin_notices', array(static::class, 'render_missing_manifest_notice'));
            return null;
        }

        // store our manifest contents.
        static::$manifest = json_decode(file_get_contents($manifestPath), true) ?: [];

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
     * Admin notice shown when no manifest was found.
     *
     * @return void
     */
    public static function render_missing_manifest_notice(): void
    {
        echo '<div class="notice notice-error"><p>Tatami: no Vite manifest found, so no theme assets are enqueued. Run <code>pnpm build</code>.</p></div>';
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
