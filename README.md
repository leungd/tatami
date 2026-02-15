# Tatami Theme

A foundational WordPress starter theme built with modern development tools.

## Features

- **Timber/Twig** - Template engine for clean, maintainable templates
- **Vite** - Fast development server and optimized production builds
- **Tailwind CSS 4** - Utility-first CSS framework with typography plugin
- **Modern JavaScript** - ES modules support with Vite

## Requirements

- PHP 8.0+
- WordPress 5.0+
- Composer
- Node.js & pnpm

## Installation

1. Clone or copy this theme into your WordPress themes directory
2. Install PHP dependencies: `composer install`
3. Install JavaScript dependencies: `pnpm install`

## Development

Start the Vite development server:
```bash
pnpm dev
```

Build for production:
```bash
pnpm build
```

Lint JavaScript:
```bash
pnpm lint
```

Format code:
```bash
pnpm format
```

## Configuration

### Tailwind
Tailwind 4 uses CSS-based configuration. Customize the theme in `src/css/tailwind.css` using `@theme` directives. Brand colors, spacing, and other design tokens are configured directly in the CSS file.

### Fluid Grid System

The theme includes a 12-column fluid grid system (`.fluid-grid`) with named grid lines for flexible layout control:

```html
<div class="fluid-grid">
    <div class="col-[content-start/content-end]">Full content width</div>
    <div class="col-[col-3/col-10]">Custom column span</div>
    <div class="col-[full-start/full-end]">Full bleed</div>
</div>
```

Use Tailwind's arbitrary value syntax for grid column placement.

### Vite Integration

Vite is integrated via a custom WordPress plugin in `vite.config.js` that:
- Writes a `build/hot` file when the dev server is running
- Cleans up the hot file on server stop
- Triggers full-page reload on `.php` and `.twig` file changes

The `lib/Vite.lib.php` class provides `asset()`, `css()`, `img()`, and `enqueue_module()` methods for resolving Vite-built assets in WordPress.

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
- `views/header.twig` - Site header
- `views/footer.twig` - Site footer
- `views/partials/menu.twig` - Menu component with dropdowns
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

This theme is designed to work with ACF (Advanced Custom Fields) options pages. Configured options are available in Twig templates via `{{ options }}`. ACF is optional — the theme gracefully handles its absence.

## Assets

### Styles
Main stylesheet: `src/css/tailwind.css`

### Scripts
Main JavaScript: `src/js/main.js`

## License

This theme is licensed under the terms specified in the LICENSE file.
