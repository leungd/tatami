# Tatami WordPress Theme

A foundational WordPress starter theme built with modern development tools including Timber/Twig, Vite, and Tailwind CSS.

## Project Overview

**Technology Stack:**
- WordPress 5.0+
- PHP 8.0+
- Timber v2/Twig templating engine
- Vite for asset bundling
- Tailwind CSS v4 with typography plugin (CSS-first configuration)
- ESLint + Prettier for code quality

## Development Commands

### Setup
```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies (using pnpm based on lockfile)
pnpm install

# Start development server
pnpm run dev
```

### Build & Deploy
```bash
# Production build
pnpm run build

# Preview production build
pnpm run preview
```

### Code Quality
```bash
# Lint JavaScript
npx eslint src/

# Format code with Prettier
npx prettier --write src/
```

## Project Structure

```
tatami/
├── lib/                    # PHP classes and theme functionality
│   ├── Tatami.lib.php     # Main theme setup and Timber context
│   ├── Theme.lib.php      # Asset enqueueing
│   └── Vite.lib.php       # Vite integration
├── views/                  # Twig templates
│   ├── base.twig          # Base HTML structure
│   ├── page.twig          # Page templates
│   ├── single.twig        # Single post template
│   ├── archive.twig       # Archive/blog template
│   └── partials/          # Reusable components
├── src/                   # Source assets
│   ├── css/tailwind.css   # Tailwind config + custom styles
│   ├── js/main.js         # Main JavaScript
│   └── img/               # Images
├── build/                 # Built assets (generated)
└── vendor/                # Composer dependencies
```

## Key Files

- `functions.php` - Theme initialization
- `style.css` - WordPress theme header
- `src/css/tailwind.css` - Tailwind v4 configuration (CSS-first)
- `vite.config.js` - Vite build configuration
- `composer.json` - PHP dependencies

## WordPress Integration

This theme requires:
- Timber plugin for Twig templating
- ACF (Advanced Custom Fields) recommended for theme options
- WordPress 5.0+ with modern PHP support

## Customization

### Adding Custom Post Types
Edit `register_post_types()` method in `lib/Tatami.lib.php`

### Adding Custom Taxonomies  
Edit `register_taxonomies()` method in `lib/Tatami.lib.php`

### Modifying Global Context
Edit `add_to_context()` method in `lib/Tatami.lib.php`

### Custom Twig Filters
Add filters in `add_to_twig()` method in `lib/Tatami.lib.php`

## Asset Management

Assets are processed through Vite:
- CSS processed with `@tailwindcss/vite` plugin
- JavaScript bundled with ES modules support
- Images optimized and copied to build directory
- Hot module replacement in development

## Tailwind v4 Configuration

Tailwind is configured via CSS-first approach in `src/css/tailwind.css`:
- `@theme { }` - Define custom design tokens (colors, fonts, spacing)
- `@source "..."` - Additional content paths to scan
- `@plugin "..."` - Load plugins
- `@variant name { }` - Apply responsive/state variants
- `@utility name { }` - Define custom utilities

## Development Notes

- Uses pnpm as package manager (based on pnpm-lock.yaml)
- ES modules enabled in package.json
- Prettier configured with Twig plugin support
- ESLint configured with Airbnb style guide
- Native CSS nesting supported (no Sass needed)
