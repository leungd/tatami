<?php

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
	 * @throws Exception
	 */
	public static function init(): string|null
	{
		static::$isHot = file_exists(static::hotFilePath());

		// are we running hot?
		if (static::$isHot) {
			static::$server = file_get_contents(static::hotFilePath());
			return static::$server . '/@vite/client';
		}

		// we must have a manifest file...
		if (!file_exists($manifestPath = static::buildPath() . '/.vite/manifest.json')) {
			throw new Exception('No Vite Manifest exists. Should hot server be running?');
		}

		// store our manifest contents.
		static::$manifest = json_decode(file_get_contents($manifestPath), true);

		return null;
	}

	/**
	 * Enqueue the Vite client module (dev server only).
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function enqueue_module(): void
	{
		// we only want to continue if we have a client.
		if (!$client = Vite::init()) {
			return;
		}

		// enqueue our client script
		wp_enqueue_script('vite-client', $client, [], null);

		// update html script type to module
		Vite::script_type_module('vite-client');
	}

	/**
	 * Return URI path to an asset.
	 *
	 * @param $asset
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function asset($asset): string
	{
		if (static::$isHot) {
			return static::$server . '/' . ltrim($asset, '/');
		}

		if (!array_key_exists($asset, static::$manifest)) {
			throw new Exception('Unknown Vite build asset: ' . $asset);
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
	 * Return URI path to an image.
	 *
	 * @param $img
	 *
	 * @return string|null
	 */
	public static function img($img): ?string
	{
		try {
			$asset = 'src/img/' . ltrim($img, '/');
			return static::asset($asset);
		} catch (Exception $e) {
			return null;
		}
	}

	/**
	 * Update html script type to module.
	 *
	 * @param string $scriptHandle
	 * @return void
	 */
	public static function script_type_module(string $scriptHandle): void
	{
		add_filter('script_loader_tag', function ($tag, $handle, $src) use ($scriptHandle) {
			if ($scriptHandle !== $handle) {
				return $tag;
			}

			return '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '-js"></script>';
		}, 10, 3);
	}

}
