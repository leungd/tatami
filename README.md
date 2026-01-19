# Tatami Theme

A foundational WordPress starter theme built with modern development tools.

## Features

- **Timber/Twig** - Template engine for clean, maintainable templates
- **Vite** - Fast development server and optimized production builds
- **Tailwind CSS** - Utility-first CSS framework with typography plugin
- **Modern JavaScript** - ES modules support with Vite

## Requirements

- PHP 7.4+
- WordPress 5.0+
- Composer
- Node.js & npm/yarn
- Timber plugin

## Installation

1. Clone or copy this theme into your WordPress themes directory
2. Install PHP dependencies: `composer install`
3. Install JavaScript dependencies: `npm install` or `yarn install`
4. Install and activate the Timber plugin

## Development

Start the Vite development server:
```bash
npm run dev
```

Build for production:
```bash
npm run build
```

## Configuration

### Tailwind
Customize Tailwind in `tailwind.config.js`. Add your brand colors, extend the default theme, and configure safelist patterns for dynamic classes.

### Theme Files

#### Core PHP Files
- `functions.php` - Main theme setup
- `lib/Tatami.lib.php` - Theme functionality, Timber context, and custom functions
- `lib/Theme.lib.php` - Asset enqueueing
- `lib/Vite.lib.php` - Vite integration for WordPress

#### Template Files (Twig)
- `views/base.twig` - Base template with HTML structure
- `views/page.twig` - Default page template
- `views/single.twig` - Single post template
- `views/archive.twig` - Archive/blog template
- `views/search.twig` - Search results template
- `views/404.twig` - 404 error template
- `views/header.twig` - Site header with navigation
- `views/footer.twig` - Site footer
- `views/menu.twig` - Menu component with dropdowns
- `views/partials/` - Reusable template partials

## Customization

### Adding Custom Post Types
Add your custom post types in the `register_post_types()` method in `lib/Tatami.lib.php`.

### Adding Custom Taxonomies
Add your custom taxonomies in the `register_taxonomies()` method in `lib/Tatami.lib.php`.

### Timber Context
Modify the global Timber context in the `add_to_context()` method in `lib/Tatami.lib.php`. This is where you can add site-wide variables accessible in all Twig templates.

### Custom Twig Filters
Add custom Twig filters in the `add_to_twig()` method in `lib/Tatami.lib.php`.

## Theme Options

This theme is designed to work with ACF (Advanced Custom Fields) options pages. Configured options are available in Twig templates via `{{ options }}`.

## Assets

### Styles
Main stylesheet: `src/scss/main.scss`

### Scripts
Main JavaScript: `src/js/main.js`

### Images
Place images in `src/img/` - they'll be processed by Vite.

## License

This theme is licensed under the terms specified in the LICENSE file.
