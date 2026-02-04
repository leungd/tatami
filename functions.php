<?php
/**
 * Tatami Theme
 *
 * @package  WordPress
 * @subpackage  Tatami
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/Vite.lib.php';
require_once __DIR__ . '/lib/Theme.lib.php';
require_once __DIR__ . '/lib/Tatami.lib.php';

Timber\Timber::init();

// Sets the directories (inside your theme) to find .twig files.
Timber::$dirname = ['views'];

new TatamiTheme();
new Theme();
