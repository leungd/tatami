<?php
/**
 * Timber starter-theme
 * https://github.com/timber/starter-theme
 *
 * @package WordPress
 * @subpackage Timber
 * @since Timber 0.1
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once(__DIR__ . '/lib/Vite.lib.php');
require_once(__DIR__ . '/lib/Theme.lib.php');
require_once(__DIR__ . '/lib/Tatami.lib.php');

Timber\Timber::init();

// Sets the directories (inside your theme) to find .twig files.
Timber::$dirname = [ 'templates', 'views' ];

new TatamiTheme();
