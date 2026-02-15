# AGENTS.md — Tatami WordPress Theme

## Identity

Tatami is a WordPress starter theme. Each site built on it is a **derivative** — the base stays lean, and site-specific code is added per project. Never add site-specific content (brand colors, client copy, hardcoded slugs) to the base theme.

## Stack

| Layer | Tool | Notes |
|-------|------|-------|
| CMS | WordPress 6.x | Classic theme (not FSE/block theme) |
| Templating | Timber v2 / Twig | PHP files are thin routers; all markup lives in `views/` |
| CSS | Tailwind CSS v4 | CSS-first config in `src/css/tailwind.css` — no `tailwind.config.js` |
| Build | Vite 5 | Dev server with HMR, manifest-based production builds |
| Fields | ACF Pro | Optional dependency — theme must work without it |
| PHP deps | Composer | Timber loaded via `vendor/autoload.php` |
| JS deps | pnpm | Lockfile is `pnpm-lock.yaml` — never use npm or yarn |
| Quality | ESLint + Prettier | Prettier has Twig + Tailwind plugins configured |

## Architecture

```
functions.php          → Bootstraps everything (autoload, init Timber, instantiate classes)
lib/Tatami.lib.php     → TatamiTheme class (extends Timber\Site) — CPTs, taxonomies, context, hooks
lib/Theme.lib.php      → Asset enqueueing via Vite integration
lib/Vite.lib.php       → Vite ↔ WordPress bridge (dev server detection, manifest reading)
views/                 → All Twig templates
  base.twig            → Root HTML shell — all page templates extend this
  partials/            → Reusable fragments (head, menu, pagination, page-header)
  macros/              → Twig macros for repeated patterns (images, nav items)
  modules/             → Self-contained content sections (services grid, map, etc.)
src/css/tailwind.css   → Tailwind config + custom utilities + component styles
src/js/main.js         → JS entry point — imports CSS, initializes modules
```

### Data flow

1. WordPress routes request → PHP template file (e.g., `page.php`)
2. PHP file builds Timber context, calls `Timber::render('page.twig', $context)`
3. Twig template extends `base.twig`, overrides blocks with page-specific content
4. Global context (menu, site, ACF options) injected via `add_to_context()` in `Tatami.lib.php`

## File conventions

### Naming
- PHP lib files: `PascalCase.lib.php`
- Twig templates: `kebab-case.twig` matching WordPress template hierarchy
- Page templates: `page-{slug}.twig` (auto-resolved by `page.php`)
- Single templates: `single-{post_type}.twig` or `single-{slug}.twig`
- Twig partials: `partials/{name}.twig`
- Twig macros: `macros/{name}.twig`
- Twig modules: `modules/{name}.twig`
- JS modules: `src/js/{name}.js` (camelCase)

### Indentation
- PHP: 4 spaces
- Everything else (JS, CSS, Twig, JSON, YAML): 2 spaces
- Defined in `.editorconfig` — respect it

## How to do common tasks

### Add a custom post type
1. Register in `lib/Tatami.lib.php` → `register_post_types()` method
2. Always set `'show_in_rest' => true` for block editor and REST API support
3. Create `single-{post_type}.twig` in `views/`
4. If you need archive pages, create `archive-{post_type}.twig`
5. Add the PHP router file (e.g., `single-{post_type}.php`) only if you need custom context beyond what `single.php` provides — usually you don't

### Add a page template
1. Create `page-{slug}.twig` in `views/` — `page.php` auto-resolves it by slug
2. If the page needs custom context (queries, ACF fields), add logic in `page.php` with a slug check
3. Extend `base.twig` and override the `content` block

### Add a reusable module
1. Create `views/modules/{name}.twig`
2. Include it from page templates: `{% include 'modules/{name}.twig' with { data: someData } %}`
3. Keep modules self-contained — they receive data via context, never query directly

### Add a Twig macro
1. Create or edit files in `views/macros/`
2. Import in templates: `{% from 'macros/image.twig' import acf_image %}`
3. Use macros for repeated HTML patterns that need parameters (images, buttons, cards)

### Add global context
Edit `add_to_context()` in `lib/Tatami.lib.php`. Available everywhere in Twig:
- `{{ site }}` — Timber site object
- `{{ menu }}` — primary nav menu
- `{{ options }}` — ACF options page fields (if ACF active)

**Keep `add_to_context()` lean.** Only put data here that is truly needed on every page (menu, site, options). Page-specific queries belong in the PHP router file for that page:
```php
// GOOD — query in the router file that needs it
// front-page.php
$context['services'] = Timber::get_posts([...]);
$context['recent_posts'] = Timber::get_posts([...]);

// BAD — querying in add_to_context() runs on every request including 404s
public function add_to_context($context) {
    $context['services'] = Timber::get_posts([...]); // don't do this
}
```

### Add JavaScript functionality
1. Create a module in `src/js/main.js` using the IIFE pattern:
```js
const MyFeature = (() => {
  const init = () => { /* ... */ };
  return { init };
})();
```
2. Call `MyFeature.init()` inside the `Utils.domReady()` callback
3. For larger features, create separate files in `src/js/` and import them

### Add custom fonts
1. Install via pnpm: `pnpm add @fontsource-variable/{font-name}`
2. Import in `src/js/main.js`: `import '@fontsource-variable/{font-name}'`
3. Set in `src/css/tailwind.css` under `@theme`: `--font-display: "{Font Name} Variable", sans-serif`

### Add brand colors
Define in `src/css/tailwind.css` under `@theme`:
```css
@theme {
  --color-brand-primary: #hexval;
  --color-brand-primary-*: /* palette via oklch or hex */;
}
```
Use as `text-brand-primary`, `bg-brand-primary-100`, etc.

## CSS rules

### Tailwind v4 CSS-first config
All Tailwind configuration lives in `src/css/tailwind.css`. Key directives:
- `@import "tailwindcss"` — loads the framework
- `@source "../../views/**/*.twig"` — tells Tailwind where to scan for classes
- `@plugin "@tailwindcss/typography"` — loads plugins
- `@theme { }` — defines design tokens (colors, fonts, spacing, breakpoints)

### Fluid grid system
The theme includes a custom `.fluid-grid` — a 12-column CSS Grid with named lines:
- Grid lines: `full-start`, `content-start`, `col-1`–`col-12`, `content-end`, `full-end`
- Place items using arbitrary grid-column values: `col-[content-start/content-end]`, `col-[col-3/col-10]`, `col-[full-start/full-end]`
- Use the grid for page-level layout. Use Tailwind's `grid` and `flex` for component-level layout.

### Fluid typography
Custom `clamp()`-based type scale defined as CSS variables (`--text-xs` through `--text-6xl`). Applied to headings in base styles. Use these for consistent responsive sizing.

### Component styles
Write component styles in `src/css/tailwind.css` using native CSS nesting. Prefer Tailwind utilities in Twig templates for one-off styling. Use CSS classes for:
- Complex components that would be unreadable as utility strings
- States that need CSS animations/transitions
- Third-party plugin styling overrides (e.g., Contact Form 7)

## PHP rules

- PHP 8.0+ syntax — use typed properties, union types, match expressions, named arguments where appropriate
- All theme logic in `lib/` classes, never in `functions.php` (it's just a bootstrapper)
- ACF is an optional dependency — always guard with `function_exists('get_fields')` or similar
- Never use `wp_head`/`wp_footer` action hooks for inline styles or scripts — use the Vite pipeline
- Register all hooks in class constructors
- WordPress coding standards for PHP (but 4-space indent, not tabs)

## Twig rules

- All templates extend `base.twig` (except partials, macros, and modules)
- Use blocks for overridable sections: `{% block content %}{% endblock %}`
- Use `{% include %}` for partials and modules, `{% from %}` for macros
- Access post data via Timber objects: `{{ post.title }}`, `{{ post.content }}`, `{{ post.thumbnail }}`
- Access ACF fields via: `{{ post.meta('field_name') }}` or `{{ options.field_name }}`
- Use Twig filters for display logic: `{{ post.date | date('F j, Y') }}`
- Never put PHP logic in Twig — if you need data transformation, do it in the PHP context

## Navigation

Build a single `<nav>` structure that works mobile-first and adapts to the desktop design via CSS/JS. Never create separate mobile and desktop nav elements with duplicate markup — one nav, progressively enhanced with responsive styles and toggling behavior. The mobile menu (hamburger, slide-out, overlay, etc.) operates on the same underlying `<nav>` and menu items.

## JavaScript rules

- ES modules (`type: "module"` in package.json)
- No jQuery — use vanilla JS with modern APIs
- Use the `Utils.domReady()` helper for initialization
- IIFE module pattern for feature organization
- Passive event listeners for scroll/touch events
- `requestAnimationFrame()` for DOM sync operations
- ARIA attributes on all interactive elements

## Accessibility requirements

- Skip-to-content link in `base.twig` (already present)
- Semantic HTML: `<nav>`, `<main>`, `<article>`, `<aside>`, `<header>`, `<footer>`
- ARIA attributes on interactive elements (menus, accordions, modals)
- Focus management for dynamic content (mobile menus, modals)
- Keyboard navigation support (Escape to close, Tab trapping)
- `<details>`/`<summary>` for native accordion behavior where possible

## Security

- SVG uploads are allowed (MIME type registered) — but only for admin users
- Always escape output in Twig: `{{ variable }}` auto-escapes; use `{{ variable | raw }}` only for trusted HTML (WYSIWYG fields)
- ACF fields that accept HTML should use `| raw` — plain text fields should not
- Disable XML-RPC pingbacks on client sites
- Disable comments site-wide unless explicitly needed

## Build & dev workflow

```bash
pnpm install          # Install JS dependencies
composer install      # Install PHP dependencies (Timber)
pnpm dev              # Start Vite dev server (HMR, full-page reload on PHP/Twig changes)
pnpm build            # Production build → build/ directory with manifest
pnpm preview          # Preview production build locally
pnpm lint             # ESLint
pnpm format           # Prettier (JS, CSS, Twig)
```

### How Vite integration works
- **Dev:** Vite writes `build/hot` file → `Vite.lib.php` detects it → assets served from dev server with HMR
- **Prod:** Vite generates `build/.vite/manifest.json` → `Vite.lib.php` reads it → WordPress enqueues hashed assets
- Entry point: `src/js/main.js` (CSS imported here, Vite extracts it automatically)
- `lib/Theme.lib.php` enqueues both the extracted CSS and the JS module via `wp_enqueue_*`

### Before committing
1. Run `pnpm build` to ensure production build succeeds
2. Run `pnpm lint` and `pnpm format`
3. Never commit `node_modules/`, `vendor/`, or `build/hot`
4. The `build/` directory and `acf-json/` should be committed for deployment

## Things to avoid

- **No block theme / FSE** — this is a classic theme using Timber/Twig
- **No `tailwind.config.js`** — Tailwind v4 uses CSS-first config exclusively
- **No Sass/SCSS/Less** — native CSS nesting + Tailwind handles everything
- **No jQuery** — vanilla JS only
- **No inline styles or scripts** via `wp_head` — use the Vite asset pipeline
- **No hardcoded URLs or paths** — use WordPress functions (`home_url()`, `get_template_directory_uri()`)
- **No direct database queries** — use WordPress/Timber APIs
- **No `echo` in PHP template files** — all output goes through Twig
- **No npm or yarn** — this project uses pnpm exclusively

## Extending for a new site

When building a new site on Tatami:
1. Copy the base theme to a new project
2. Define brand colors and fonts in `src/css/tailwind.css` `@theme` block
3. Register custom post types and taxonomies in `lib/Tatami.lib.php`
4. Set up ACF field groups — use `acf-json/` for version control (local JSON)
5. Build page templates in `views/` following the naming conventions above
6. Extract reusable sections into `views/modules/` and `views/partials/`
7. Add JS interactivity in `src/js/main.js` using the module pattern
8. Query and pass data to templates via PHP context in the appropriate router file
