# AGENTS.md — Tatami WordPress Theme

## Identity

Tatami is a WordPress starter theme. Each site built on it is a **derivative** — the base stays lean, and site-specific code is added per project. Never add site-specific content (brand colors, client copy, hardcoded slugs) to the base theme.

## Stack

| Layer | Tool | Notes |
|-------|------|-------|
| CMS | WordPress 6.x | Classic theme (not FSE/block theme) |
| Templating | Timber v2 / Twig | PHP files are thin routers; all markup lives in `views/` |
| CSS | Tailwind CSS v4 | CSS-first config in `src/css/tailwind.css` — no `tailwind.config.js` |
| Build | Vite 8 | Dev server with HMR, manifest-based production builds |
| Fields | ACF Pro | Optional dependency — theme must work without it |
| PHP deps | Composer | Timber loaded via `vendor/autoload.php` |
| JS deps | pnpm | Lockfile is `pnpm-lock.yaml` — never use npm or yarn |
| Quality | ESLint + Prettier | Prettier has Twig + Tailwind plugins configured |

## Architecture

```
functions.php          → Bootstraps everything (autoload, init Timber, instantiate classes)
lib/Site.lib.php       → Tatami\Site class (extends Timber\Site) — CPTs, taxonomies, context, hooks, hardening
lib/Queries.lib.php    → Tatami\Queries class — reusable Timber queries (featured images, services, etc.)
lib/Assets.lib.php     → Asset enqueueing via Vite integration
lib/Vite.lib.php       → Tatami\Vite — Vite ↔ WordPress bridge (dev server detection, manifest reading)
views/                 → All Twig templates
  base.twig            → Root HTML shell — all page templates extend this
  partials/            → Reusable fragments (head, hero, pagination, post-list)
  macros/              → Twig macros for repeated patterns (images, nav items)
  modules/             → Self-contained content sections (services grid, map, etc.)
src/css/tailwind.css   → Tailwind config + custom utilities + component styles
src/js/main.js         → JS entry point — imports CSS, initializes modules
```

### Data flow

1. WordPress routes request → PHP template file (e.g., `page.php`)
2. PHP file builds Timber context, calls `Timber::render('page.twig', $context)`
3. Twig template extends `base.twig`, overrides blocks with page-specific content
4. Global context (menu, site, ACF options) injected via `add_to_context()` in `Site.lib.php`

## File conventions

### Naming
- PHP lib files: `PascalCase.lib.php`
- Twig templates: `kebab-case.twig` matching WordPress template hierarchy
- Page templates: `page-{slug}.twig` (auto-resolved by `page.php`)
- Single templates: `single-{post_type}.twig` or `single-{slug}.twig`
- Category archive templates: `archive-category-{slug}.twig` (other archives: `archive-{post_type}.twig`)
- Twig partials: `partials/{name}.twig`
- Twig macros: `macros/{name}.twig`
- Twig modules: `modules/{name}.twig`
- JS modules: `src/js/{name}.js` (camelCase)

### Indentation
- PHP: 4 spaces
- Everything else (JS, CSS, Twig, JSON, YAML): 2 spaces
- Defined in `.editorconfig` — respect it

### Comments
Comment only to record something the code cannot state for itself — a non-obvious constraint, a gotcha, a deliberate-looking-wrong decision. A comment reads the same whether written today or a year ago: it states the constraint, not what an edit did or how you arrived at it.
- **Don't** reference the task, the fix, or the prior state: no `// changed to…`, `// now uses…`, `// added guard for…`, `// fix:…`, `// previously we…`. Git records what changed and when; the file shouldn't.
- **Do** write comments that stand on their own and stay true independent of history: `// ACF is optional — this path runs when the plugin is absent`.
- **Sparse, not decorative.** Never add banner or section-divider comments (`// ===== Helpers =====`, `/* --- Config --- */`, `# ----------`) or a running play-by-play that narrates your reasoning or restates what each line plainly does. Rows of comment scaffolding read as machine-generated and bury the few notes that carry real information.
- **File/unit headers are the one exception.** A reusable unit — macro, partial, module, `lib/` class — may open with a header comment giving its purpose, parameters, and a short usage example (see the `{# … #}` block atop `views/macros/image.twig`). That documents an *interface*, worth stating once. Comments *inside* the body still follow the sparse rule.
- When in doubt, omit. Prefer no comment over filler.

## How to do common tasks

### Add a custom post type
1. Register in `lib/Site.lib.php` → `register_post_types()` method
2. Always set `'show_in_rest' => true` for block editor and REST API support
3. Create `single-{post_type}.twig` in `views/`
4. If you need archive pages, create `archive-{post_type}.twig`
5. Add the PHP router file (e.g., `single-{post_type}.php`) only if you need custom context beyond what `single.php` provides — usually you don't

### Add a page template
1. Create `page-{slug}.twig` in `views/` — `page.php` auto-resolves it by slug
2. If the page needs custom context (queries, ACF fields), add logic in `page.php` with a slug check
3. Extend `base.twig` and override the `content` block

### Front page & posts page (house routing pattern)

The standard setup is a static "Home" page + a "Blog" posts page assigned under Settings → Reading.

- The front page renders through `front-page.php` → `front-page.twig` (WP hierarchy name).
- **The posts page never routes through `page.php`** — WordPress serves it via `home.php`. `home.php` participates in the page-template convention: it resolves `page-{slug}.twig` from the assigned Blog page's slug (so a site calling it "News" gets `page-news.twig`), sets `post` to the Blog page (its title/ACF fields drive the header), and falls back to `home.twig` → `index.twig`.
- Naming rule: the front page uses `front-page.twig`; **every other admin-created page — including the posts page — uses `page-{slug}.twig`**.

### Featured images (house tool)

Routers for singular views assign `$context['featured_image'] = Tatami\Queries::featured_image_with_fallback($post);` — a Timber image object with parent fallback (a page without a thumbnail inherits its parent's). Templates read `featured_image.src`, `featured_image.alt`, `featured_image.width`, `featured_image.height`; `partials/hero.twig` demonstrates consumption as an optional hero. Derivative heroes build on this key. (Older derivatives consume `featured_image_src`/`featured_image_alt` — a breaking difference; don't retrofit them.)

### Hero (house tool)

Every page's hero renders from `partials/hero.twig`, pulled in by `{% block hero %}` in `base.twig`. **Never hand-roll a `<header>` in a `single-*` / `page-*` template** — override the block and reuse the partial. The partial exposes two named blocks so derivatives *extend* the shell instead of duplicating it:

- `heroMedia` — the optional full-bleed featured image
- `heroBody` — the title region (defaults to `<p>{{ title }}</p>`)

```twig
{% block hero %}
  {% embed 'partials/hero.twig' with { title, featured_image } %}
    {% block heroBody %}
      {{ parent() }}
      <p class="mt-4 text-sm">{{ post.date|date('F j, Y') }}</p>
    {% endblock %}
  {% endembed %}
{% endblock %}
```

**Exactly one `<h1>` per page, on the semantically-primary heading.** The hero title is a label (`<p>`) whenever the `<h1>` lives elsewhere; it is promoted to the `<h1>` only when the title is itself the whole heading (e.g. a person's name, or a utility/listing page). Each page's single `<h1>` is the derivative's responsibility. Two clarifications:

- The `<h1>` may come from the WYSIWYG body (`post.content`) **or a dedicated ACF heading field**. Structured CPT singles have no WYSIWYG `<h1>` — they render an ACF field as the body `<h1>` and keep the hero title as the `<p>` label (reference: the LK site's `single-service.twig`, where the `intro_statement` ACF field is the body `<h1>`).
- **Heading level is semantic, not visual.** A `<p class="text-6xl">` name above an `<h1 class="text-5xl">` keyword line is correct markup. Do not equate "biggest text" with "the `<h1>`."

Why it matters: SEO is core business for this shop — the content team writes one keyword-rich `<h1>` per page ("Dynamic advocate and advisor in high-profile public interest and public law matters"), distinct from the short page title. Promoting the short title to `<h1>` starves the keyword heading.

On content-less listing/utility pages (`archive`, `search`, `404`, the blog `index`) the router-set `title` *is* the whole heading — those base templates override `heroBody` to render `<h1>{{ title }}</h1>` (a working reference for the promotion case). Content pages keep the `<p>` label and get their `<h1>` from the body.

`pnpm lint` enforces a **floor only**: it fails any page template that hand-rolls a `<header>`, including a per-section `<article><header>` — push that markup into a module (modules never extend `base.twig`); page-level meta belongs in `heroBody`. The guardrail is blind to `<h1>` placement — a template that inverts the heading rule still passes lint; that check happens in review (see "Definition of done").

### Add a reusable module
1. Create `views/modules/{name}.twig`
2. Include it from page templates: `{% include 'modules/{name}.twig' with { data: someData } %}`
3. Keep modules self-contained — they receive data via context, never query directly
4. Guard on the data so the module no-ops when it's absent (`{% if services %}…{% endif %}`). This lets the same module be dropped into any template; it only renders where the router supplied data.

### Add a reusable query
Any post query with custom args lives as a **static method on `Tatami\Queries`** (`lib/Queries.lib.php`), named for intent — **even if only one router calls it**. Routers only ever call named `Queries` methods or the bare default `Timber::get_posts()` (the main loop); trivial default-loop queries stay inline. A `Queries` method is a boundary, not a speculative reuse hook — the class's purpose is router thinness, not just reuse, so a single-caller method does not violate "no abstractions for single-use code." Centralizing keeps query logic in one place, lets routers share it when they do overlap (e.g. `front-page.php` and `single.php` both fetch services), and keeps the `Tatami\Site` class focused on setup rather than data fetching.
```php
// lib/Queries.lib.php
namespace Tatami;

public static function recent_posts(int $exclude_id = 0, int $count = 3) {
    $args = ['post_type' => 'post', 'posts_per_page' => $count, 'orderby' => 'date', 'order' => 'DESC'];
    if ($exclude_id) {
        $args['post__not_in'] = [$exclude_id];
    }
    return Timber::get_posts($args);
}
```
```php
// router file — front-page.php, single.php, etc.
$context['blog_posts'] = Tatami\Queries::recent_posts($post->ID); // exclude current post
```
Rules:
- One method per logical query; name it for intent (`services()`, `recent_posts()`), not the post type.
- Accept parameters for the variations callers actually need (count, exclusions) with sensible defaults — don't fork into near-duplicate methods.
- Routers stay thin: they call a helper and assign to context, nothing more.

### Add a Twig macro
1. Create or edit files in `views/macros/`
2. Import in templates: `{% from 'macros/image.twig' import acf_image %}`
3. Use macros for repeated HTML patterns that need parameters (images, buttons, cards)

**Card-like patterns have three shapes — pick by how content is supplied:**
- Content fully described by parameters (title, excerpt, image, url) → **macro**
- Wraps arbitrary inner markup (a slot/`children` equivalent) → **`embed`** a partial with `{% block %}`s
- The surrounding grid/list that loops and renders the cards → **module**

Extract the *markup*, never the styling. A long utility string is a signal to reach
for one of the above, not to write a CSS class — the utilities stay just as visible,
only in one place.

### Icons & SVG assets
Pick the shape by asset class:
- **Uniform, single-color, name-selected glyphs** (UI icons, simple social marks) → a macro (`macros/icon.twig`): one shared `currentColor` shell, selected by name.
- **Bespoke or multi-color artwork with its own dimensions** (logos, illustrations) → a per-file partial under `views/svg/`, included raw.
- Decision tell: can it be one `currentColor`, and is it one of many uniform glyphs? → macro. Multi-color bespoke? → file. Route social icons through the macro — keep them themeable.

Sanctioned sources: **Lucide** for UI glyphs (ISC, `lucide-static`) and **Simple Icons** for brand/social marks (CC0). Their scopes are non-overlapping by design — Lucide ships no brand logos. Two expectations to hold: brand marks are trademarks (nominative use is fine) and can be pulled upstream (LinkedIn was), so the occasional hand-drawn fallback is normal; and Lucide (outline) won't stroke-match Simple Icons (solid) — that mismatch is expected, not a bug.

### Add global context
Edit `add_to_context()` in `lib/Site.lib.php`. Available everywhere in Twig:
- `{{ site }}` — Timber site object
- `{{ menu }}` — primary nav menu
- `{{ options }}` — ACF options page fields (if ACF active)

**Keep `add_to_context()` lean.** Only put data here that is truly needed on every page (menu, site, options). Page-specific queries belong in the PHP router file for that page, and the query itself lives in `Tatami\Queries` (see "Add a reusable query" below):
```php
// GOOD — router calls a named query helper
// front-page.php
$context['services']   = Tatami\Queries::services();
$context['blog_posts'] = Tatami\Queries::recent_posts();

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
Self-hosted fonts (the default):
1. Install via pnpm: `pnpm add @fontsource-variable/{font-name}`
2. Import in `src/js/main.js`: `import '@fontsource-variable/{font-name}'`
3. Set in `src/css/tailwind.css` under `@theme`: `--font-display: "{Font Name} Variable", sans-serif`

Hosted fonts that can't be self-hosted (Adobe Fonts / Typekit): register their own `wp_enqueue_style` in `Site.lib.php` — never by editing `Assets.lib.php`.

The underlying distinction: `Vite.lib.php` + `Assets.lib.php` are **base plumbing** — keep them pristine so they diff clean against the base. `Site.lib.php` + `Queries.lib.php` are the **site surface** — extend them freely (CPTs, taxonomies, hooks, queries, and hosted-font enqueues all live here).

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

**`.fluid-grid` places its _direct children_ only** (that's how CSS Grid works — `col-[…]` on a grandchild does nothing). So put `.fluid-grid` on the section, give each region **one** placed wrapper, and let that region's content flow *inside* it with normal flow (`space-y-*`, flex) — don't stamp `col-[…]` on every element.

```twig
{# GOOD — one placed wrapper; children flow inside it #}
<section class="fluid-grid py-24">
  <div class="col-[content-start/content-end] space-y-8 lg:col-[col-3/col-11]">
    <p class="text-sm font-bold">{{ __('/ Section', 'tatami') }}</p>
    <h2 class="font-display text-5xl">{{ post.title }}</h2>
    <div class="prose max-w-none">{{ post.content|raw }}</div>
  </div>
</section>

{# BAD — same placement stamped on every sibling; space-y-8 on a leaf is a no-op #}
<section class="py-24">
  <div class="fluid-grid">
    <p  class="col-[content-start/content-end] space-y-8 lg:col-[col-3/col-11] …">…</p>
    <h2 class="col-[content-start/content-end] space-y-8 lg:col-[col-3/col-11] …">…</h2>
    <div class="col-[content-start/content-end] space-y-8 lg:col-[col-3/col-11] …">…</div>
  </div>
</section>
```

Two tells you got it wrong: the same `col-[…]` string on more than one sibling (wrap them in a single placed `<div>` instead), or `space-y-*` on an element with no children to space (it belongs on the flowing parent). A region that genuinely spans *different* columns than its neighbor is its own placed child — `partials/hero.twig` is the reference: a full-bleed `heroMedia` (`col-[full-start/full-end]`) beside a content-column body.

### Fluid typography
Custom `clamp()`-based type scale defined as CSS variables (`--text-xs` through `--text-6xl`). Applied to headings in base styles. Use these for consistent responsive sizing.

### Component styles
Prefer Tailwind utilities in Twig templates. When a pattern repeats, extract the
**markup** (macro, `embed`, or module — see "Add a Twig macro") rather than the styling.
A long utility string means extract a Twig fragment, never mint a CSS class.

Write component styles in `src/css/tailwind.css` (native CSS nesting) only for things
templating can't solve:
- Styling markup you don't author — WYSIWYG/`the_content()` output, Gutenberg blocks,
  third-party plugin overrides (e.g., Contact Form 7)
- CSS features utilities can't express cleanly — complex keyframe animations, intricate
  `:nth-child`/sibling logic, pseudo-element content
- Design-system primitives the theme already owns this way (`.fluid-grid`, fluid type scale)
- Avoid `@apply` as a reuse strategy — extract Twig fragments instead. The remaining
  legitimate use is third-party/plugin overrides; if you must `@apply` there, keep it in
  the main `@import "tailwindcss"` graph (`src/css/tailwind.css` or files it imports), or
  it fails in v4 with "unknown utility" unless the file pulls in the theme via `@reference`.

## PHP rules

- PHP 8.0+ syntax — use typed properties, union types, match expressions, named arguments where appropriate
- All theme logic in `lib/` classes, never in `functions.php` (it's just a bootstrapper)
- All `lib/` classes live in the `Tatami\` namespace
- Post queries with custom args go in `Tatami\Queries` (`lib/Queries.lib.php`) as intent-named static methods — a router never inlines a custom `Timber::get_posts([...])`, even for a single caller (see "Add a reusable query"). Add new lib files to the `require_once` list in `functions.php`.
- ACF is an optional dependency — always guard with `function_exists('get_fields')` or similar
- Never use `wp_head`/`wp_footer` action hooks for inline styles or scripts — use the Vite pipeline
- Register all hooks in class constructors
- WordPress coding standards for PHP (but 4-space indent, not tabs)

## Twig rules

- All templates extend `base.twig` (except partials, macros, and modules)
- Use blocks for overridable sections: `{% block content %}{% endblock %}`
- Use `{% include %}` for partials and modules, `{% from %}` for macros, and
  `{% embed %}` for fragments that wrap caller-supplied markup (cards with slots, callouts)
- Access post data via Timber objects: `{{ post.title }}`, `{{ post.content }}`, `{{ post.thumbnail }}`
- Access ACF fields via: `{{ post.meta('field_name') }}` or `{{ options.field_name }}`
- Use Twig filters for display logic: `{{ post.date | date('F j, Y') }}`
- Never put PHP logic in Twig — if you need data transformation, do it in the PHP context
- `pnpm format` formats all Twig templates except `views/base.twig` and `views/header.twig`,
  listed in `.prettierignore` — the Twig plugin's parser cannot handle `base.twig`'s dynamic
  `<{{tag}}>` wrapper, nor `header.twig`'s `{% if %}` tags inside an element's attribute list.
  Format those files by hand: 2-space indent, same as every other template. Any new template
  hitting either pattern must also be added to `.prettierignore`.

## Navigation

Build a single `<nav>` structure that works mobile-first and adapts to the desktop design via CSS/JS. Never create separate mobile and desktop nav elements with duplicate markup — one nav, progressively enhanced with responsive styles and toggling behavior. The mobile menu (hamburger, slide-out, overlay, etc.) operates on the same underlying `<nav>` and menu items.

The base ships a semantic skeleton in `header.twig` (`<nav aria-label="Primary">`, `menu.items` loop, `aria-current="page"` on the current item, `rel="noopener noreferrer"` on `_blank` targets). Sites needing dropdown submenus use the disclosure pattern: a real `<button>` with `aria-expanded` (toggled in JS) + `aria-controls`, an `sr-only`/visible label (not `title=`), Escape to close, `aria-hidden="true" focusable="false"` on decorative SVGs. Do not use `aria-haspopup` for plain disclosure submenus.

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
- **Contrast is a token-time decision:** when defining `@theme` brand colors, every foreground/background pairing the design will use must meet WCAG AA (4.5:1 text, 3:1 large text/UI components). Record the intended pairings as comments next to the tokens. Never introduce a text-on-brand combination without checking it.
- **Focus visibility:** never remove focus outlines without a replacement; every interactive element needs a visible `:focus-visible` state with ≥3:1 contrast against its surroundings.
- **Motion:** wrap all non-essential animation in `motion-safe:` (Tailwind) or `@media (prefers-reduced-motion: no-preference)`. No autoplaying movement > 5s without a pause control. (The base ships a global reduced-motion reset in `tailwind.css`.)
- **State is programmatic, not just visual:** any UI state shown by color/style (current nav item, selected tab, open accordion, current page) must also be expressed in ARIA (`aria-current`, `aria-expanded`, `aria-selected`).
- **Images:** every `<img>` gets `alt` — from the media-library alt field for content images, `alt=""` for decorative; inline decorative SVGs get `aria-hidden="true" focusable="false"`.
- **Forms:** every control gets a programmatic `<label>` (not placeholder-as-label); errors are announced (`aria-describedby` + `role="alert"` or a live region).
- **Touch targets:** interactive targets ≥ 24×24 CSS px (44×44 preferred for primary mobile controls).
- **Language:** all user-facing strings go through `__('…', 'tatami')` in PHP and `{{ __('…', 'tatami') }}` in Twig (return-based — never `_e()` in Twig); no concatenated sentence-building in templates (use `|format`).

## Security

- Twig autoescaping is ON (`autoescape: 'html'`, set in `Tatami\Site::set_twig_environment_options()`): `{{ variable }}` escapes for HTML. Use `| raw` only for trusted HTML — `post.content`, `post.excerpt` (WYSIWYG output), and `site.language_attributes`. WordPress functions that echo (`function('wp_footer')`, `{% do action(...) %}`) bypass escaping; their output is WP's responsibility.
- ACF fields that accept HTML should use `| raw` — plain text fields must not.
- SVG uploads are allowed for administrators only (`manage_options` gate in `Tatami\Site::add_svg_mime_type()`). Pair with a sanitizer plugin (e.g. Safe SVG) on client sites.
- XML-RPC pingbacks are disabled in the base (`Tatami\Site::disable_xmlrpc_pingbacks()`).
- Comments are disabled site-wide in the base; a site that genuinely needs them removes the three comment filters and the admin-menu removal in `Tatami\Site::__construct()`.
- Author archives return 404 (`Tatami\Site::disable_author_archives()`) — unused on client sites and an enumeration vector. A site that needs them removes that hook and uses `Timber::get_user()`.
- Password-protected content renders Timber's password form (filter enabled in `Tatami\Site::__construct()`).
- Front-end titles are entity-decoded (`Tatami\Site::decode_title_entities()`, hooked on `the_title` at `template_redirect` time) so Twig's autoescape encodes them exactly once — WP's default filter chain pre-encodes ampersands and emits numeric references for quotes/dashes, which would otherwise double-encode. The hook is deliberately scoped to front-end template rendering; REST and feed output keep WP's encoding. The site name gets the same treatment in `add_to_context()` (`get_bloginfo('name')` pre-encodes too).

## Build & dev workflow

```bash
pnpm install          # Install JS dependencies
composer install      # Install PHP dependencies (Timber)
pnpm dev              # Start Vite dev server (HMR, full-page reload on PHP/Twig changes)
pnpm build            # Production build → build/ directory with manifest
pnpm preview          # Preview production build locally
pnpm lint             # ESLint + Twig hero-guardrail (no page template may hand-roll a <header>)
pnpm format           # Prettier (JS, CSS, Twig)
```

### How Vite integration works
- **Dev:** Vite writes `build/hot` file → `Vite.lib.php` detects it → assets served from dev server with HMR
- **Prod:** Vite generates `build/.vite/manifest.json` → `Vite.lib.php` reads it → WordPress enqueues hashed assets
- Entry point: `src/js/main.js` (CSS imported here, Vite extracts it automatically)
- `lib/Assets.lib.php` enqueues both the extracted CSS and the JS module via `wp_enqueue_*`

### Before committing
1. Run `pnpm build` to ensure production build succeeds
2. Run `pnpm lint` and `pnpm format`
3. Never commit `node_modules/`, `vendor/`, or anything under `build/`
4. `build/` is never committed — Tatami is a base theme; sites build assets at deploy time (`pnpm build`). A missing build fails soft: the site renders unstyled and logs the error. `acf-json/` should be committed.

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
- **No hand-rolled page headers** — a `single-*`/`page-*` template must not contain its own `<header>`; override `{% block hero %}` and reuse `partials/hero.twig` (enforced by `pnpm lint`)

## Definition of done (template work)

Before calling any template work complete, load and eyeball — with `WP_DEBUG` on: the front page, the blog home *with more than one page of posts* (pagination must render), a category archive, a search with results and with none, and a 404. Run one keyboard-only pass: skip link, full nav including any submenus, focus visible throughout.

Confirm exactly one `<h1>` per page, on the keyword/primary heading, with the page title as a `<p>` label unless the title is the whole heading (see "Hero"). This check lives in review, not CI — the lint guardrail only catches hand-rolled `<header>`s and is blind to `<h1>` placement; it will pass a template that inverts the heading rule.

## Doc integrity

AGENTS.md must describe the repo as it is. If a change makes a statement in AGENTS.md false (files, behavior, versions, security posture), updating AGENTS.md is part of the change.

## Extending for a new site

When building a new site on Tatami:
1. Copy the base theme to a new project
2. Define brand colors and fonts in `src/css/tailwind.css` `@theme` block
3. Register custom post types and taxonomies in `lib/Site.lib.php`
4. Set up ACF field groups — use `acf-json/` for version control (local JSON). **Namespace group and field keys with a short site prefix** (`lk_`, `jm_`, …) — e.g. `group_lk_service`, `field_lk_service_intro_statement`. ACF keys are a global namespace within a WP install (unique across the theme, every plugin, and ACF's own auto-keys); unprefixed generic keys (`group_service`, `field_image`) risk silent last-one-wins collisions. Never rename ACF keys on a live site — field values are stored in the DB keyed by the field key, so renaming orphans existing content. The prefix rule is going-forward; existing unprefixed sites are grandfathered.
5. Build page templates in `views/` following the naming conventions above
6. Extract reusable sections into `views/modules/` and `views/partials/`
7. Add JS interactivity in `src/js/main.js` using the module pattern
8. Add reusable queries to `Tatami\Queries` (`lib/Queries.lib.php`), then call them from the appropriate router file and assign to context
9. **Backport rule:** the site repo keeps a `BACKPORT.md` list of fixes that aren't site-specific (a11y helpers, Timber API corrections, structural CSS, security gating). Whenever a session makes a change it judges non-site-specific, it appends the bullet to `BACKPORT.md` itself — don't rely on the human to remember. Never treat `BACKPORT.md` as complete: it's the primary write-time capture, not the full truth; a periodic drift audit against the base is the backstop for what slips through.

## Agent skills

### Issue tracker

Issues are tracked as GitHub issues in `leungd/tatami` via the `gh` CLI; external PRs are not a triage surface. See `docs/agents/issue-tracker.md`.

### Triage labels

Canonical triage roles map 1:1 to their default label strings (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.