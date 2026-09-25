# Tatami

A WordPress base theme for professional-services sites, built on Timber/Twig, Tailwind CSS 4 and Vite. Each site is a **derivative**: a copy of this theme that pulls updates from here and keeps everything site-specific to itself.

## Requirements

- PHP 8.0+
- WordPress 6.x
- Composer
- Node.js and pnpm (see `.nvmrc`)
- ACF Pro (optional at runtime; the theme renders without it)
- Yoast SEO (required at launch by house policy; owns SEO output and the schema graph)

## Installation

1. Copy the theme into `wp-content/themes/`
2. `composer install`
3. `pnpm install`

## Commands

```bash
pnpm dev        # Vite dev server with HMR
pnpm build      # Production build → build/ (never committed; built at deploy)
pnpm preview    # Preview the production build
pnpm lint       # ESLint + the Twig hero guardrail
pnpm test       # Node tests (linter, ACF recipes) + PHP tests of the pure helpers
pnpm format     # Prettier for JS, CSS and Twig
```

## Where things are

| Path | What it is |
|---|---|
| `AGENTS.md` | The rules: stack, conventions, house-tool invariants, definition of done. Read this first. |
| `docs/` | One reference doc per house tool and convention (hero, schema, attribution, FAQs, related posts, ACF fields, launch checklist) |
| `recipes/acf/` | ACF field-group JSON for the house tools, with a `SITE` placeholder to copy into a site's `acf-json/` |
| `CONTEXT.md` | Glossary of the terms the theme and its docs use (Firm, Professional, Service, Host, …) |
| `lib/` | PHP classes in the `Tatami\` namespace: site setup, queries, assets, Vite bridge, schema, attribution, social profiles |
| `views/` | Twig templates: `base.twig`, page and single templates, `partials/`, `macros/`, `modules/` |
| `src/css/tailwind.css` | Tailwind configuration (CSS-first), design tokens, the fluid grid and type scale |
| `src/js/main.js` | JavaScript entry point; imports the CSS |
| `tests/` | PHP test runner for the pure helpers |
| `scripts/` | The Twig linter and the node tests |

## Building a site on Tatami

Clone the theme, point its remote at this repo as a pull-only `upstream`, then follow "Extending for a new site" in `AGENTS.md`. Before launch, work through `docs/launch.md`.

Base changes are made here by the maintainer. A derivative reports a base bug or fix as a GitHub issue on this repo; it never pushes or opens a pull request.

## License

See `LICENSE`.
