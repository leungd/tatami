# Audit Remediation — Design

**Date:** 2026-07-07
**Inputs:** `AUDIT.md` (54 findings, severity-recalibrated for base-as-product risk) and `AUDIT-REVIEW.md` (independent verification — all 54 CONFIRMED, plus four out-of-scope observations).
**Goal:** close all 54 findings in the Tatami base theme, add the §9 AGENTS.md guardrails, and produce a backport list for the johnson-miller derivative — without touching that repo.

## Scope decisions (settled with Derek)

1. **Scope:** all 54 findings actioned, plus the §9 guardrail additions to AGENTS.md. MISC-1 is documentation-only per the audit's own calibration.
2. **SEC-2 autoescape:** enable it — `timber/twig/environment/options` filter setting `'autoescape' => 'html'`, followed by a template-wide `| raw` audit for trusted HTML.
3. **DOC-2 deployment:** `build/` stays gitignored. Tatami is a skeleton/base theme; built artifacts never go back to the repo. AGENTS.md's "Before committing" section is rewritten to match.
4. **SEC-8 hardening:** implemented in the base (not reworded to per-site guidance). XML-RPC pingback methods removed, comments disabled site-wide, contradicting `comment-form`/`comment-list` HTML5 support and `automatic-feed-links` dropped. A derivative needing comments deletes the hardening call.
5. **PAT-7 namespacing:** all of `lib/` moves into a `Tatami\` namespace. Breaking difference applies to the *next* derivative only; existing sites are not retrofitted (same policy the audit sets for PAT-4).
6. **Derivative scope:** base theme only. johnson-miller's needed fixes are recorded in `BACKPORT-johnson-miller.md` as a deliverable, actioned separately in that project.
7. **Execution shape:** one branch (`audit-remediation`), eight dependency-ordered work packages, one commit each, every commit leaving the theme in a working state.

Judgment calls approved during design review:

- **DOC-4 version:** `style.css` bumped to `6.0.0` to match `package.json` (that is the actual current major); README/AGENTS.md version references aligned (WordPress 6.x, Vite 8).
- **MISC-2:** `body { overflow-x: hidden }` is **removed**, not commented — in a base theme it hides exactly the overflow bugs the fluid grid exists to prevent.
- **SEC-7:** mutate the existing script tag (preserve WP/plugin-added attributes) rather than adopting the Script Modules API — least invasive of the audit's options.
- **COR-3:** author archives return 404 (via query/`template_redirect` handling in the Site class) rather than redirect; `author.php` is deleted.

## Work packages

Each package lists the finding IDs it closes. Finding details, line numbers, and rationale live in AUDIT.md — this spec does not restate them.

### WP1 — Mechanical hygiene
**Closes: PAT-6, PAT-8, DOC-4**

- Reindent the 7 tab-indented PHP files to 4 spaces (isolated first so later diffs stay readable).
- Replace stale Timber-starter boilerplate headers in `404.php` and `index.php` with the `@subpackage Tatami` header style used by the other routers.
- Version alignment: `style.css` → `Version: 6.0.0`; README "WordPress 5.0+" → 6.x; AGENTS.md "Vite 5" → Vite 8.

### WP2 — PHP architecture: `Tatami\` namespace
**Closes: PAT-7, PAT-9, PAT-11**

- Namespace all `lib/` classes: `TatamiTheme` → `Tatami\Site`, `Theme` → `Tatami\Assets`, `Vite` → `Tatami\Vite`. Update `functions.php` bootstrapping and all internal references. File names follow the class rename (`Site.lib.php`, `Assets.lib.php`, `Vite.lib.php`, later `Queries.lib.php`).
- ACF admin-CSS inline closure → named method, bailing early when ACF is absent.
- Remove redundant `add_theme_support('menus')`.

### WP3 — Security & runtime plumbing
**Closes: SEC-1, SEC-2 (config half), SEC-3, COR-4, SEC-8, COR-3, COR-5, SEC-4, SEC-5, SEC-7, PAT-10, MISC-3, MISC-1, YAG-5, YAG-6**

In `Tatami\Site`:
- SVG mime filter gated on `current_user_can('manage_options')` (capability, not role, per AUDIT-REVIEW). AGENTS.md later notes the Safe SVG-style sanitizer recommendation for client sites.
- Autoescape enabled via `timber/twig/environment/options` (`'autoescape' => 'html'`). The per-template `| raw` audit happens in WP5.
- `timber/post/content/show_password_form_for_protected` → `__return_true`; the broken `single-password.twig` branch in `single.php` deleted.
- Hardening method: strip pingback methods from `xmlrpc_methods`, disable comments site-wide (close support + hide admin UI), drop `comment-form`/`comment-list` from HTML5 theme support, drop `automatic-feed-links`.
- Author archives 404: handled in the Site class, `author.php` deleted. Also closes the `/?author=N` enumeration vector.
- `add_filter('timber/twig', …)` registered so `add_to_twig()` is live.

In `Tatami\Vite` / `Tatami\Assets`:
- Hot mode short-circuited unless `wp_get_environment_type()` is `local` or `development`.
- Missing manifest fails soft: logged warning + admin notice, no enqueue, site renders unstyled — never fatal.
- `script_type_module()` mutates the existing tag (adds `type="module"`) instead of rebuilding it, preserving nonces/attributes.
- Stylesheet enqueued with `media: 'all'`.
- `Vite::img()` deleted (YAG-5 — no callers, no `src/img/`; restorable trivially when a site needs it).
- Post-formats registration deleted (YAG-6).
- MISC-1: `get_fields('option')` kept as-is with a comment documenting the global per-request cost of options-page bloat.

In `vite.config.js`:
- Hot file written in `server.httpServer.once('listening', …)` using the resolved address, so auto-incremented ports are recorded correctly.

### WP4 — Routers + the `Tatami\Queries` scaffold
**Closes: COR-2, A11Y-4, PAT-1, PAT-3, PAT-5, PAT-4, YAG-3, YAG-4, DOC-1**

- `home.php` rewritten per the audit's COR-2 pattern: template candidates `page-{blog-page-slug}.twig` → `home.twig` → `index.twig`; `$context['post']` set to the Blog page; `$context['title']` from the Blog page title (fallback `'Blog'`). Encodes the house rule: front page = `front-page.twig`, every other admin-created page — including the posts page — = `page-{slug}.twig`.
- Redundant main-query assignments deleted: `Timber::get_posts()` in `archive.php`, `home.php`, `search.php`, `front-page.php` (`author.php` is deleted in WP3); `front-page.php`'s redundant `Timber::get_post()` too (AUDIT-REVIEW out-of-scope observation 2).
- `single.php` template order: `single-{slug}.twig` before `single-{post_type}.twig`.
- Category archives keyed by term slug (`archive-category-{slug}.twig`), not numeric ID.
- Dead context keys deleted: `slug` (page.php), `post_type` (search.php), `posts` (front-page.php).
- `lib/Queries.lib.php` created as `Tatami\Queries` with `featured_image_with_fallback(Post $post): ?Image` (returns the Timber image object of the post's thumbnail, falling back to the parent's, else `null`; templates read `featured_image.src` / `featured_image.alt`) as its first occupant — the PAT-4 reshape of `setup_featured_image()`, which is removed from the Site class. `page.php`/`single.php` assign `$context['featured_image']` explicitly. Key rename is a documented breaking difference for the next derivative.
- `views/macros/image.twig` scaffolded with the audit's corrected `acf_image` macro (Timber v2 `get_image()`, `width`/`height` attributes, `loading`/`decoding`, escape-safe under the new autoescape).
- `views/modules/` created with a minimal stub module demonstrating the guard-on-data pattern.
- One base template demonstrates featured-image consumption (optional hero in `page-header.twig`) so the tool is legible (YAG-3).

### WP5 — Twig templates
**Closes: COR-1, COR-8, YAG-1, YAG-2, PAT-2, A11Y-1, A11Y-3, A11Y-5, A11Y-6, A11Y-7, A11Y-8, COR-6, COR-7, SEC-6, plus SEC-2's template half**

- Extract `views/partials/post-list.twig` (params: `posts`, `empty_message`): `<time datetime>` dates, `post.excerpt` (not deprecated `preview`), and `{% include 'partials/pagination.twig' %}` replacing the fatal `{{ posts.pagination }}` echo. `index.twig`/`archive.twig`/`search.twig` become thin wrappers. Empty-state guard uses `posts is not empty` (bare `{% if posts %}` is always truthy on a PostQuery — AUDIT-REVIEW observation 1).
- `pagination.twig`: `aria-current="page"` on the current page, `aria-hidden` on disabled prev/next, `viewBox` casing, hardcoded blue/gray palette neutralized for a base theme.
- `base.twig`: skip link → `#main`; `role="main"` dropped; `{% do action('wp_body_open') %}` after `<body>`; `wp_footer` moved outside `{% block footer %}`; `wp_head` moved outside `{% block head %}`.
- `menu.twig` deleted (with its dropdown machinery, dead JS hooks, and hardcoded colors). `header.twig` gains the ~10-line semantic nav skeleton: `<nav aria-label="Primary">`, `menu.items` loop, `aria-current="page"`, conditional `target` with `rel="noopener noreferrer"`. Dropdown/disclosure guidance moves to AGENTS.md (WP8) instead of shipping unused component code.
- Autoescape `| raw` audit across every template: `post.content` and other trusted-HTML outputs marked; everything else left to escape.

### WP6 — CSS restructure
**Closes: PAT-12, PAT-13, A11Y-2, YAG-7, MISC-2**

`src/css/tailwind.css` reorganized to the johnson-miller-proven structure (labeled sections: Theme → Base → Layout → Accessibility helpers → Components):
- Fluid `--text-*` scale moved into `@theme`, with `--text-*--line-height` tokens reconciled.
- `html { @apply text-base lg:text-lg }` double-scaling dropped.
- Base heading styles (`h1`–`h6`) applying the fluid scale added under `@layer base`, making AGENTS.md's claim true.
- `.screen-reader-text` / `.skip-link` visually-hidden-with-focus-reveal block backported from johnson-miller, brand colors neutralized.
- `prefers-reduced-motion` reset backported.
- No-op `--typography-body-max-width` deleted.
- `body { overflow-x: hidden }` removed.

### WP7 — i18n
**Closes: I18N-1, MISC-4**

- `Text Domain: tatami` header in `style.css`; `load_theme_textdomain('tatami')` in theme setup.
- All user-facing strings (~12: router titles, empty states, pagination labels, skip link) through `__('…', 'tatami')` in PHP and `{{ __('…', 'tatami') }}` in Twig — return-based, which also fixes the `_e()` Twig-4 yield-mode hazard.

### WP8 — Documentation + deliverables
**Closes: DOC-2, DOC-3, DOC-5, §9 guardrails**

AGENTS.md:
- "Before committing" rewritten: `build/` is never committed in the base; derivatives choose their own deploy strategy (the SEC-5 fail-soft makes a missed build non-fatal).
- Security section made true: autoescape on (with the `| raw` doctrine), SVG admin-gated + sanitizer recommendation, hardening now shipped in base.
- New sections: "Front page & posts page" routing pattern (posts page routes through `home.php`, `page-{slug}.twig` resolution); navigation disclosure pattern (the a11y guidance that replaces `menu.twig`); the featured-image house tool.
- §9 guardrails added: accessibility rails (contrast-at-token-time, focus visibility, motion, programmatic state, images, forms, touch targets, language), the definition-of-done checklist, the doc-integrity rule, the backport rule.
- Naming/architecture references updated for the `Tatami\` namespace and new file names.

README: dead-API references corrected — the "Custom Twig Filters" section kept (the extension point is live after COR-5) but updated for the namespaced class; the `img()` section and the `views/partials/menu.twig` file-list entry deleted. Version references were already aligned in WP1 (DOC-4); WP8 only rechecks them in the doc-integrity pass.

Deliverable: `BACKPORT-johnson-miller.md` — the fixes that repo needs, actioned separately: `search.twig` pagination echo (live crash path), `author.php` fatal, `viewBox` casing in carried-over partials, plus a pointer to adopt the backport rule going forward.

## Verification

**Per-package:** `php -l` on all touched PHP files; `pnpm build` + `pnpm lint` + `pnpm format --check` where JS/CSS/Twig changed.

**Empirical (audit's own method — harness scripts against the vendored Timber/Twig autoload):**
- post-list + pagination partial compiles and renders without the COR-1 fatal;
- autoescape verified on (a hostile string escapes; a `| raw` output does not);
- `acf_image` macro compiles.
- Other templates at minimum compile-checked through the real Twig environment.

**End-of-branch:**
- Full `pnpm build`, `pnpm lint`, `pnpm format`.
- Grep sweep asserting no finding signature remains: `{{ posts.pagination }}`, `viewbox`, `post.preview`, `new Timber\User`, `single-password.twig`, tab-indented PHP, `role="main"`, `_e(` in Twig, etc.
- Doc-integrity pass: reread AGENTS.md and README against the final code — making the docs true is half the point of this remediation.

## Error-handling design (the two engineered failure paths)

- **Missing Vite manifest:** logged warning + admin notice; no enqueue; site renders unstyled. Never fatal.
- **Stale `build/hot` outside local/development:** ignored; production assets served from manifest.

## Process & git

- Branch `audit-remediation` off `main`; eight commits matching WP1–WP8, each message referencing the finding IDs it closes.
- `AUDIT.md` / `AUDIT-REVIEW.md` remain untracked working documents.
- Nothing is pushed without Derek's explicit go-ahead.
- After the spec is approved: writing-plans skill produces the step-by-step implementation plan; execution follows executing-plans with the per-package verification gates above.
