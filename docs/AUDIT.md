# Tatami Theme Audit

**Date:** 2026-07-07 · **Scope:** all theme source (13 PHP, 17 Twig, JS/CSS entries, build/tooling config) · **Excluded:** `vendor/`, `node_modules/`, `build/`

**Method:** full read of every source file; cross-reference map of template includes, context keys, and hooks; API verification against the vendored Timber v2.5.1 / Twig 3.27.1 source; empirical test of the Twig object-echo behavior (run against the theme's own vendor autoload). Findings marked **[verified]** were confirmed against vendor source or by running code — none are speculative.

Severity: **Critical** = fatal error or blank page on a core path · **High** = security exposure or compliance failure · **Medium** = broken promise, dead feature, or maintainability trap · **Low** = polish, consistency, hygiene.

**Recalibration (2026-07-07):** severities were adjusted after reviewing a real derivative site (johnson-miller) and usage context: author archives and password-protected content are effectively never used on client sites, and listing views get rebuilt per-project. Severity now reflects *base-theme-as-product* risk — how likely a defect is to ship inside a derivative — rather than raw fresh-install behavior. The derivative also serves as evidence throughout: findings it independently fixed (skip link, `.screen-reader-text`) are confirmed real and marked **[backport]**; findings it inherited unchanged (`{{ posts.pagination }}` in search, `viewbox`, SVG gating, author fatal) are confirmed to propagate.

---

## 1. Runtime correctness (found while auditing — blocking, so listed first)

### COR-1 · CRITICAL · `{{ posts.pagination }}` fatals on every paginated view
- **Where:** `views/index.twig:20`, `views/archive.twig:20`, `views/search.twig:20`
- **Issue:** `Timber\Pagination` has no `__toString()` (verified in `vendor/timber/timber/src/Pagination.php`), and `PostQuery::pagination()` always returns a Pagination object for main queries — so the `{% if posts.pagination %}` guard is always truthy and the echo throws `Error: Object of class Timber\Pagination could not be converted to string`. **[verified empirically** — reproduced with the theme's vendored Twig**]**
- **Why it matters:** the blog index, all archives, and search results fatal whenever they contain posts. The theme's core listing pages cannot render. **This defect propagated:** the johnson-miller derivative still carries the identical `{{ posts.pagination }}` echo in its production `search.twig:16` — a live crash path on a shipped client site, which is exactly the compounding failure mode a base theme must not seed.
- **Fix:** replace the inline echo with `{% include 'partials/pagination.twig' %}` — the partial already exists (see YAG-1), iterates `posts.pagination.pages`, and carries its own correct emptiness guard. Fix johnson-miller's `search.twig` while at it.

### COR-2 · HIGH · Blog home renders blank — `home.twig` doesn't exist
*(Downgraded from Critical: every derivative rebuilds the blog listing per-project — johnson-miller created its own `blog.twig` + title — so this won't ship inside a client site. It's still a broken default in the base.)*
- **Where:** `home.php:11-12`
- **Issue:** `Timber::render(array('home.twig'), $context)` — `views/home.twig` does not exist and no fallback is given, so the posts page outputs nothing. Note this is a missing template, not intentional minimalism — a placeholder-philosophy base would still render *something* through `index.twig`.
- **Fix — and an opportunity to encode the house routing pattern:** the standard workflow on these sites is a static "Home" page + "Blog" posts page assigned under Settings → Reading. That pattern is good (editors get real pages for menus/SEO/ACF fields; `front-page.php` → `front-page.twig` aligns with the WP hierarchy) — but note the routing subtlety it hides: **the posts page never goes through `page.php`**, WordPress serves it via `home.php`, so a `page-blog.twig` will never be auto-resolved by the page-slug convention. To make the convention actually work for the posts page, `home.php` should participate in it:
  ```php
  $blog_page = get_option('page_for_posts') ? Timber::get_post(get_option('page_for_posts')) : null;

  $templates = array( 'home.twig', 'index.twig' );
  if ( $blog_page ) {
      array_unshift( $templates, 'page-' . $blog_page->post_name . '.twig' );
      $context['post'] = $blog_page; // the Blog page itself — its title/ACF fields drive the header
  }
  $context['title'] = $blog_page ? $blog_page->title() : 'Blog';

  Timber::render( $templates, $context );
  ```
  This makes `page-blog.twig` resolve exactly as intended (slug-driven, so a site calling it "News" works too), gives the template the Blog page's own fields alongside the `posts` loop, and keeps `index.twig` as the safety net. The pattern deserves a short AGENTS.md section ("Front page & posts page") so future agents know the posts page routes through `home.php`, not `page.php`.
- **Naming rule (decided in review):** the front page uses the hierarchy name `front-page.twig`; **every other admin-created page — including the posts page — uses `page-{slug}.twig`**. Rationale: `page-{slug}` is Tatami's strongest convention ("find the page in WP-Admin, its template is `page-{slug}.twig`") and should hold without exceptions on the authoring side; `home.twig` is actively misleading in a static-front-page workflow (the admin page named "Home" is *not* its template); a bare `blog.twig` (what johnson-miller did) is a third naming family with no generating rule and a hardcoded slug. `home.twig`/`index.twig` remain in the fallback chain for hierarchy-minded devs, but `page-{slug}.twig` is the one you create.

### COR-3 · HIGH · Author archives fatal — `new Timber\User()` is not constructible
*(Downgraded from Critical per usage context: author archives are never used on client sites. It stays High rather than lower because the URLs are still publicly reachable — `/?author=1` resolves on any WP site regardless of whether the site links to it, so this is a discoverable 500 on every derivative. The identical code is live in johnson-miller today.)*
- **Where:** `author.php:14`
- **Issue:** Timber v2 made `User::__construct()` protected (verified at `vendor/timber/timber/src/User.php:173`); instantiating it directly throws a fatal `Error`. This is Timber 1.x syntax that survived the v2 migration.
- **Fix:** since author archives are never used, the better move is to **disable them in the base** — redirect author queries to home (or 404) in `TatamiTheme`. That fixes the fatal, removes an entire unused route class (YAGNI), and closes author-enumeration probing (`/?author=N` username disclosure) as a hardening bonus. If a future site ever needs author pages, the one-line Timber fix is `Timber::get_user($wp_query->query_vars['author'])`.

### COR-4 · LOW · Password-protected posts render a blank page — `single-password.twig` doesn't exist
*(Downgraded from Medium: password-protected content is ~never used on client sites, and this path fails closed.)*
- **Where:** `single.php:20-21`
- **Issue:** the password branch renders `single-password.twig`, which is not in `views/`. Timber fails to resolve the template and outputs nothing.
- **Fix:** delete the branch entirely and enable Timber's built-in password form instead (one filter — see SEC-3, which fixes both findings at once).

### COR-5 · MEDIUM · `add_to_twig()` is dead — the `timber/twig` filter is never registered
- **Where:** `lib/Tatami.lib.php:137-141` (constructor at lines 6-27 registers `timber/context` but not `timber/twig`)
- **Issue:** the documented extension point for custom Twig filters (README "Custom Twig Filters" section points here) is never hooked, so anything added to it silently does nothing.
- **Why it matters:** a booby trap for every derivative site: a developer (or LLM) follows the docs, adds a filter, and gets no error and no effect.
- **Fix:** add `add_filter('timber/twig', array($this, 'add_to_twig'));` to the constructor.

### COR-6 · MEDIUM · `wp_body_open()` is missing
- **Where:** `views/base.twig:10`
- **Issue:** no `wp_body_open` hook after `<body>`. Required since WP 5.2; analytics, GTM, and accessibility plugins inject here.
- **Fix:** `{% do action('wp_body_open') %}` immediately after the `<body>` tag.

### COR-7 · MEDIUM · `wp_footer` lives inside an overridable block
- **Where:** `views/base.twig:40-43`
- **Issue:** `{{ function('wp_footer') }}` sits inside `{% block footer %}`. Any child template overriding that block without `{{ parent() }}` silently drops `wp_footer` — killing all enqueued scripts, admin bar, and plugin output.
- **Fix:** move the `wp_footer` call outside the block (directly before `</body>`), leaving the block for markup only. Same reasoning argues for keeping `wp_head` outside `{% block head %}` (or documenting the `parent()` requirement).

### COR-8 · LOW · `{{ post.preview }}` is deprecated Timber 1.x API
- **Where:** `views/index.twig:14`, `views/archive.twig:14`, `views/search.twig:14`
- **Issue:** `Post::preview()` is `@deprecated 2.0.0` (verified in vendor source); it still works but fires deprecation notices under WP_DEBUG.
- **Fix:** `{{ post.excerpt }}`.

---

## 2. Security

### SEC-1 · HIGH · SVG uploads enabled for all users, unsanitized — contradicting AGENTS.md
- **Where:** `lib/Tatami.lib.php:49-52`
- **Issue:** `add_svg_mime_type()` adds `image/svg+xml` for every role with upload capability. No capability gate, no sanitization. AGENTS.md explicitly promises "SVG uploads are allowed … but only for admin users" — the code has no such restriction.
- **Why it matters:** SVG is an XSS vector (inline `<script>`, event handlers, foreign objects). Any author/editor account — or a compromised one — can upload a script-bearing SVG that executes in wp-admin when viewed.
- **Fix:** gate the filter: `if (!current_user_can('administrator')) { return $mimes; }`. Recommend pairing with an SVG sanitizer (e.g. the Safe SVG plugin) on client sites, and note that in AGENTS.md.

### SEC-2 · HIGH · Twig autoescape is OFF — AGENTS.md asserts the opposite
- **Where:** theme-wide; no `timber/twig/environment/options` filter anywhere; Timber default is `'autoescape' => false` (verified at `vendor/timber/timber/src/Loader.php:378`)
- **Issue:** AGENTS.md's security section states "`{{ variable }}` auto-escapes; use `| raw` only for trusted HTML." That is false under Timber's defaults — every `{{ ... }}` in this theme outputs raw. `{{ post.title }}`, `{{ item.title }}`, `{{ title }}` etc. are unescaped in both element and attribute contexts.
- **Why it matters:** the theme's entire escaping posture rests on a config that isn't enabled. Derivative sites will add ACF-driven output assuming auto-escaping. Lower-privileged roles' titles are kses-filtered but not attribute-safe; plugin-sourced strings get no protection at all. **The false belief is already steering real code:** johnson-miller's `lib/Queries.lib.php` decodes HTML entities in term names with the comment "decoding here lets Twig escape it exactly once" — a deliberate decision premised on escaping that never happens, so those term names render decoded *and* unescaped.
- **Fix (pick one, do it in the base):**
  1. **Preferred:** enable autoescape — `add_filter('timber/twig/environment/options', fn($opts) => array_merge($opts, ['autoescape' => 'html']));` then audit the few trusted-HTML outputs (`post.content`, `post.excerpt`, `wp_head`/`wp_footer` functions are already marked safe by Timber) and add `| raw` where needed. The theme is small enough that this is a one-hour job now and impossible later.
  2. Or correct AGENTS.md to state the truth and mandate explicit `| e` / `| esc_attr` — much weaker.

### SEC-3 · MEDIUM · Password-protected pages leak their content
*(Downgraded from High per usage context: clients essentially never password-protect content. Kept at Medium rather than Low because the failure mode is the bad kind — **silent**. The feature appears in the WP editor on every derivative; the one time a client uses it, it doesn't protect, and nothing looks wrong. The fix is one line.)*
- **Where:** `page.php` (no password check) + Timber default
- **Issue:** Timber's `Post::content()` only substitutes the password form when the `timber/post/content/show_password_form_for_protected` filter returns true — the default is **false**, and when false it falls through and returns the real content (verified at `vendor/timber/timber/src/Post.php:1268-1290`). `page.php` has no `post_password_required()` branch, so a password-protected page renders its full content to anyone.
- **Fix:** add once in `TatamiTheme::__construct()`: `add_filter('timber/post/content/show_password_form_for_protected', '__return_true');`. This also makes `single.php`'s broken branch (COR-4) unnecessary — delete it.

### SEC-4 · MEDIUM · Stale `build/hot` file points production at a dead dev server
- **Where:** `lib/Vite.lib.php:41-47`; cleanup only via signal handlers in `vite.config.js`
- **Issue:** hot-mode detection is purely "does `build/hot` exist." The dev server cleans it on SIGINT/SIGTERM, but a crash, `kill -9`, or power loss leaves it behind — after which every front-end request loads its JS/CSS from `http://localhost:5173` (broken site for everyone but the developer). If the file is ever deployed, production does the same.
- **Fix:** short-circuit hot mode unless `wp_get_environment_type() === 'local' || 'development'`. Cheap and eliminates the whole class of failure.

### SEC-5 · MEDIUM · Missing manifest throws an uncaught exception → front-end WSOD
- **Where:** `lib/Vite.lib.php:50-52`, thrown inside `wp_enqueue_scripts` via `lib/Theme.lib.php:22`
- **Issue:** fresh checkout without `pnpm build` (or a deploy that missed the `build/` dir — see DOC-2) throws `Exception('No Vite Manifest exists…')` uncaught during enqueue → fatal on every page including wp-login-adjacent front-end flows.
- **Fix:** fail soft — log/admin-notice and return without enqueueing, so the site renders unstyled instead of dying.

### SEC-6 · LOW · `target="{{ item.target }}"` without `rel="noopener"`
- **Where:** `views/partials/menu.twig:9`
- **Issue:** menu items opened with `_blank` get no `rel="noopener noreferrer"` (reverse-tabnabbing; also a Lighthouse flag). Modern browsers imply `noopener` for `_blank`, but the theme supports "WordPress 5.0+" per README.
- **Fix:** output `rel="noopener noreferrer"` when `item.target == '_blank'`, or use Timber's `item.rel`.

### SEC-7 · LOW · `script_type_module()` rebuilds the script tag from scratch
- **Where:** `lib/Vite.lib.php:166-175`
- **Issue:** the filter discards the original tag, dropping anything WP or plugins added (CSP nonces, `integrity`, custom attributes).
- **Fix:** WP ≥ 6.3 supports `wp_enqueue_script(..., ['strategy' => ...])` and WP ≥ 6.5 has the Script Modules API (`wp_enqueue_script_module()`), which handles `type="module"` natively. Alternatively mutate the existing tag with `wp_get_script_tag()`/`str_replace` instead of replacing it.

### SEC-8 · LOW · AGENTS.md security doctrine not implemented anywhere
- **Where:** AGENTS.md "Security" section vs. codebase
- **Issue:** "Disable XML-RPC pingbacks" and "Disable comments site-wide unless explicitly needed" are stated as theme policy but no code implements either (and `theme_supports()` actively adds `comment-form`/`comment-list` HTML5 support and `automatic-feed-links`).
- **Fix:** decide the contract: either ship the hardening in the base (a small `Hardening` method: `add_filter('xmlrpc_methods', …)` removing pingback methods, comment disabling) or reword AGENTS.md to "do this per-site." Right now the doc reads as a promise the base doesn't keep.

---

## 3. AODA / WCAG 2.1 AA

### A11Y-1 · HIGH · Skip link points to an anchor that doesn't exist
- **Where:** `views/base.twig:12` (`href="#content"`) vs. `views/base.twig:20` (`<main id="main">`)
- **Issue:** the skip-to-content link targets `#content`; no element has that id. Activating it does nothing.
- **Why it matters:** WCAG 2.4.1 (Bypass Blocks) — the theme's single bypass mechanism is broken, and AODA audits check exactly this. It also fails silently, so every derivative site inherits it.
- **Fix:** `href="#main"` (or add `id="content"` to `<main>`). **[backport]** johnson-miller already fixed this exact line (`href="#main"`) — the fix exists downstream and was never brought home.

### A11Y-2 · MEDIUM · `.skip-link` / `.screen-reader-text` classes are never defined
- **Where:** `views/base.twig:12` references them; `src/css/tailwind.css` defines neither
- **Issue:** with no visually-hidden styling, the skip link renders as a permanently visible plain link at the top of every page. Not a WCAG failure (visible is allowed) — but it looks broken, and `screen-reader-text` is the class WP core and plugins emit for SR-only text, so plugin output will also display visibly.
- **Fix:** define the standard visually-hidden pattern for `.screen-reader-text` (clip-path/absolute) with a `:focus` reveal for `.skip-link`, in `src/css/tailwind.css` under `@layer base` (or apply Tailwind's `sr-only focus:not-sr-only` utilities in the template). **[backport]** johnson-miller's `tailwind.css:172-202` contains a complete, well-commented implementation of exactly this — copy it up (with the brand colors in the `:focus` state neutralized).

### A11Y-3 · MEDIUM · Navigation partial is not accessible as shipped
- **Where:** `views/partials/menu.twig` (orphaned — see YAG-2 — but audited as the base theme's nav reference implementation)
- **Issues:**
  1. **Line 20:** `aria-expanded="false"` is static — no JS anywhere updates it (`src/js/main.js` is an empty scaffold; `data-submenu-toggle` has zero behavior).
  2. **Mobile submenus are unreachable:** `data-submenu-content` is `hidden` and only revealed by `lg:group-hover`/`lg:group-focus-within` — below `lg` there is no mechanism at all to open a submenu.
  3. **Line 22:** the toggle's only accessible name is `title="Open submenu"` — weak (not announced consistently, invisible to voice control users); no state-aware label.
  4. **Lines 24-28:** decorative SVG lacks `aria-hidden="true"` / `focusable="false"`.
  5. The partial outputs a bare `<ul>` — no `<nav aria-label="…">` landmark (WCAG 1.3.1 / best practice; AGENTS.md requires semantic `<nav>`).
  6. **Line 20:** `aria-haspopup="true"` on a disclosure button is the wrong pattern — disclosure needs only `aria-expanded` (+ `aria-controls`); `haspopup` implies a `menu` role widget.
- **Why it matters (revised):** the premise that derivatives copy this partial turned out to be false — johnson-miller carried it verbatim but *unused*, and built its real nav inline in `header.twig` with a **better** pattern (`<nav aria-label="Primary">`, `aria-current="page"`, conditional `target`, working `MobileNav` JS toggling `aria-expanded` with focus management). The risk isn't copying; it's that the base's version misleads whoever *does* reach for it.
- **Fix:** contingent on the YAG-2 decision. If `menu.twig` is deleted (recommended), this finding dissolves — put the a11y nav pattern in AGENTS.md guidance instead, using johnson-miller's header as the reference. If kept, fix as originally noted: `<nav aria-label>` wrapper, real toggle JS, `sr-only` label, `aria-hidden` SVG, drop `aria-haspopup`.

### A11Y-4 · LOW · Blog home sets no title → no `<h1>`
*(Downgraded and narrowed from Medium: per usage context, front-page.twig being a bare placeholder is intentional — every derivative replaces it wholesale with designed content that carries its own h1 (johnson-miller's does). The remaining inconsistency is `home.php`: `index.php` sets `title = 'Blog'` but the router that actually serves the blog index doesn't — and the derivative had to add it.)*
- **Where:** `home.php` (no `$context['title']`); `views/partials/page-header.twig:1` no-ops without it
- **Fix:** `$context['title'] = 'Blog';` in `home.php` (folds into the COR-2 fix).

### A11Y-5 · MEDIUM · Pagination current page lacks `aria-current="page"`
- **Where:** `views/partials/pagination.twig:55-59, 77-81`
- **Issue:** the current page is styled visually (blue background) but carries no programmatic state.
- **Fix:** add `aria-current="page"` to the current-page `<span>`. While there: the disabled prev/next `<span>`s could use `aria-hidden="true"` or `aria-disabled="true"` for clarity.

### A11Y-6 · LOW · Dates not marked up as `<time>`
- **Where:** `views/index.twig:11`, `views/archive.twig:11`, `views/search.twig:11`
- **Fix:** `<time datetime="{{ post.date('c') }}">{{ post.date }}</time>` in the (extracted — PAT-2) post-list partial.

### A11Y-7 · LOW · `viewbox` attribute casing invalid (5 occurrences)
- **Where:** `views/partials/menu.twig:26`, `views/partials/pagination.twig:19, 38` (+2 more)
- **Issue:** SVG requires `viewBox`. The HTML parser's attribute-adjustment table silently corrects it when served as text/html, so it works today — but it's invalid markup that breaks the moment the fragment is reused in an XML/JSX context, and linters/validators flag it.
- **Fix:** rename to `viewBox`.

### A11Y-8 · LOW · `role="main"` is redundant on `<main>`
- **Where:** `views/base.twig:20`
- **Fix:** drop the attribute.

*(Contrast, focus-visible styling, target size, form labels, and motion cannot be audited in a colorless base theme — they're covered as guardrails in §9 instead, per our discussion.)*

---

## 4. Coding patterns & consistency

### PAT-1 · MEDIUM · Redundant `Timber::get_posts()` in five routers
- **Where:** `archive.php:30`, `author.php:12`, `home.php:10`, `search.php:20`, `front-page.php:10`
- **Issue:** Timber v2's `Timber::context()` already populates `posts` with the main query (verified at `vendor/timber/timber/src/Timber.php:1248-1257`). Re-assigning `Timber::get_posts()` is dead weight and implies to future maintainers that it's required.
- **Fix:** delete the assignments; keep `Timber::get_posts()` only where a router genuinely deviates from the main query.

### PAT-2 · MEDIUM · Identical 28-line post loop duplicated across three templates
- **Where:** `views/index.twig`, `views/archive.twig`, `views/search.twig` (lines 4-29, byte-for-byte identical except the empty-state string)
- **Issue:** violates the theme's own AGENTS.md rule ("a repeated pattern → extract a Twig fragment"). Three copies means three places to fix COR-1, COR-8, A11Y-6.
- **Fix:** extract `views/partials/post-list.twig` (taking `posts` and an `empty_message`); the three templates become 6-line wrappers. `search.twig` could then honestly fall back to `archive.twig` per its router's template array.

### PAT-3 · MEDIUM · `single.php` template order shadows slug-specific templates
- **Where:** `single.php:23-27` — candidates are `single-{post_type}.twig`, then `single-{slug}.twig`, then `single.twig`
- **Issue:** WordPress hierarchy convention is most-specific-first. As written, once a derivative site creates `single-post.twig` (routine), no `single-{slug}.twig` can ever match a standard post. AGENTS.md documents both conventions without noting one disables the other.
- **Fix:** swap the first two entries.

### PAT-4 · MEDIUM · `setup_featured_image()` is an awkward API shape for a house tool
- **Where:** `lib/Tatami.lib.php:61-79`; called from `page.php:15` and `single.php:14` as `$context['site']->setup_featured_image($post, $context)`
- **Issue:** the tool itself is established house practice (see YAG-3 reframe), but its shape isn't: reaching through the context array to call a **by-ref mutator on the Site object** cuts against the theme's own layering (Site is for setup per AGENTS.md; this is data-fetching — `TatamiQueries` territory), and by-ref `&$context` splatting three loose keys is harder to trace than a returned value.
- **Fix:** keep the behavior, move and reshape it — a static helper on the promised `TatamiQueries` returning a value (the Timber image object, or a small array) that routers assign explicitly: `$context['featured_image'] = TatamiQueries::featured_image_with_fallback($post);`. Since johnson-miller consumes the current `featured_image_src`/`featured_image_alt` key names, do the rename in the base and note it as a breaking difference for the *next* derivative (don't retrofit existing sites).

### PAT-5 · LOW · Category archive template key uses the numeric ID
- **Where:** `archive.php:24` — `'archive-' . get_query_var('cat') . '.twig'` yields `archive-7.twig`
- **Issue:** every other convention in the theme (and AGENTS.md) is slug-based; the post-type branch on line 27 uses the slug. Nobody will ever create `archive-7.twig` on purpose.
- **Fix:** use the term slug (`get_queried_object()->slug`), e.g. `archive-category-{slug}.twig`.

### PAT-6 · LOW · PHP files indented with tabs; standard says 4 spaces
- **Where:** `lib/Tatami.lib.php`, `lib/Theme.lib.php`, `lib/Vite.lib.php`, `archive.php`, `author.php`, `search.php`, `single.php` (verified) vs. `.editorconfig` (`[*.php] indent_size = 4`, `indent_style = space`) and AGENTS.md
- **Fix:** one-time reindent; consider adding PHP to a format script (e.g. `pint`/`php-cs-fixer`) so it can't drift again.

### PAT-7 · LOW · Generic global class names `Theme` and `Vite`
- **Where:** `lib/Theme.lib.php:3`, `lib/Vite.lib.php:3`
- **Issue:** unnamespaced single-word classes in WordPress's global namespace are collision bait — a plugin defining `Vite` (increasingly plausible) fatals the site.
- **Fix:** a `Tatami\` namespace across `lib/` (with the `TatamiTheme` name kept or aliased), or at minimum `TatamiVite`/`TatamiAssets`.

### PAT-8 · LOW · Stale copy-paste boilerplate headers
- **Where:** `404.php:2-10` and `index.php:2-14` — `@subpackage Timber`, `@since Timber 0.1`, "Methods for TimberHelper can be found in the /functions sub-directory" (no such directory; TimberHelper is Timber 1.x)
- **Fix:** normalize to the `@subpackage Tatami` header used by the other routers.

### PAT-9 · LOW · ACF admin-CSS hook is an inline closure among named-method hooks
- **Where:** `lib/Tatami.lib.php:16-24`
- **Issue:** every other hook in the constructor targets a named method (the AGENTS.md pattern); this one embeds a closure with an inline CSS string. Also runs on every admin page load.
- **Fix:** move to a named method; bail early when ACF is absent.

### PAT-10 · LOW · Stylesheet enqueued with `media 'screen'`
- **Where:** `lib/Theme.lib.php:27`
- **Issue:** print views get zero CSS (Tailwind preflight included), producing broken print output.
- **Fix:** `'all'`.

### PAT-11 · LOW · `add_theme_support('menus')` is redundant
- **Where:** `lib/Tatami.lib.php:125`
- **Issue:** `register_nav_menus()` two lines later implies it.
- **Fix:** delete line 125.

### PAT-12 · LOW · Heading typography contradicts AGENTS.md and renders h1 as body text
- **Where:** `src/css/tailwind.css` — the `:root` fluid type variables exist (lines 77-90), but no base heading styles apply them; AGENTS.md claims they are "applied to headings in base styles"
- **Issue:** Tailwind preflight resets `h1`–`h6` to `font-size: inherit; font-weight: inherit`, so the `<h1>` emitted by `page-header.twig` is visually indistinguishable from body text on every page.
- **Fix:** add an `@layer base` block styling `h1`–`h6` with the fluid scale (this is exactly the "design-system primitive" case AGENTS.md carves out for CSS), or update AGENTS.md to say headings must be classed per-template.

### PAT-13 · MEDIUM · Fluid type scale lives in `:root`/`@layer base` instead of `@theme` — Tailwind 4 anti-pattern
- **Where:** `src/css/tailwind.css:73-90` (base) vs. johnson-miller `tailwind.css:13-35` (the corrected structure)
- **Issue:** the base redefines `--text-xs`–`--text-6xl` in a `:root` block inside `@layer base`, shadowing Tailwind's `@theme` variables at runtime via cascade order. It works (v4 utilities compile to `font-size: var(--text-*)`), but it's the wrong surface: the values are invisible to `theme()` and design-token introspection, correctness depends on layer ordering rather than declared config, the paired `--text-*--line-height` tokens are never reconciled with the new sizes, and it contradicts AGENTS.md's own doctrine that "`@theme { }` defines design tokens." The derivative restructured this correctly — token overrides inside `@theme`, where they regenerate the actual utilities. Additionally, base `src/css/tailwind.css:73-75` (`html { @apply text-base lg:text-lg }`) layers breakpoint scaling *on top of* the clamp-based scale — double fluidity that makes real rendered sizes hard to reason about; the derivative dropped it.
- **What the base already gets right (for the record):** CSS-first config (`@import` / `@source` / `@plugin` ordering), no `tailwind.config.js`, native nesting, `.fluid-grid` as an owned primitive — the file's skeleton is correct; it's the token placement that predates the current best practice.
- **Fix:** adopt the derivative's structure as the base structure: (1) move the fluid `--text-*` scale into `@theme` (with `--text-*--line-height` set where the defaults no longer fit); (2) drop the `html` `@apply` sizing; (3) organize into the same labeled sections (Theme → Base → Layout → Accessibility helpers → Components); (4) backport the `.screen-reader-text` block (A11Y-2) and the `prefers-reduced-motion` reset pattern. This is also the natural moment to fix PAT-12 (heading base styles) and YAG-7 (dead typography var).

---

## 5. YAGNI / dead code

### YAG-1 · MEDIUM · `views/partials/pagination.twig` (145 lines) is never included
- **Where:** `views/partials/pagination.twig`; zero `{% include %}` references (verified by grep)
- **Issue:** the theme's largest template is orphaned — while the three listing templates crash trying to inline-echo the pagination object it was built to render (COR-1).
- **Fix:** wire it into the extracted post-list partial (PAT-2). It's the fix for COR-1, not a deletion candidate — but hardcoded palette classes (`bg-blue-600`, `text-gray-*`) should be neutralized for a base theme.

### YAG-2 · MEDIUM · `views/partials/menu.twig` is never included — and real projects don't use it
- **Where:** `views/partials/menu.twig`; `views/header.twig` is a one-line placeholder comment
- **Issue:** (a) no template includes it; the base theme ships no navigation at all despite `register_nav_menus()` and `{{ menu }}` context. (b) It guards on `{% if items %}` but nothing defines `items` — even if included, it silently renders nothing unless the caller passes `with { items: menu.items }`. (c) Its interactive behavior depends on JS that doesn't exist (A11Y-3). (d) It hardcodes brand-ish colors (`text-white`, `text-blue-300`, `fill="#fff"`) in a theme whose identity rule is "no site-specific styling."
- **Field evidence:** johnson-miller answers the "would a derivative use this?" question — it carried the file byte-for-byte, never included it, and wrote its actual nav inline in `header.twig` (looping `menu.items` directly) with its own mobile-overlay pattern in JS. Navigation is design-specific per site; a 59-line dropdown implementation with dead JS hooks is boilerplate that no project has cashed in.
- **Fix (recommended):** delete `menu.twig` and its dropdown machinery. What the base should carry instead is small: a *semantic skeleton* in `header.twig` (`<nav aria-label="Primary">` + `menu.items` loop + `aria-current` — ~10 neutral lines, roughly what johnson-miller wrote by hand) and an AGENTS.md nav section documenting the disclosure pattern for sites that need dropdowns (toggle `aria-expanded`, Escape to close, `sr-only` labels). That gives every project the correct starting point without shipping an unused component to maintain.

### YAG-3 · LOW · Featured-image context has no consumer *in the base* — a house tool that looks like dead code
*(Reframed from Medium/dead-code after review: this is a deliberate common tool — johnson-miller consumes `featured_image_src`/`featured_image_alt` in five views (hero images on `blog.twig`, `single.twig`, `page.twig`, `single-team.twig`, `404.twig`). The problem isn't the tool, it's that the base gives no evidence it's a tool.)*
- **Where:** `lib/Tatami.lib.php:61-79` + `page.php:15` + `single.php:14`; no base view references `featured_image*`, and AGENTS.md never mentions it
- **Issue:** to anyone (or any LLM) reading the base alone, this is indistinguishable from dead code — this audit initially flagged it for deletion, which is exactly the misread a future maintainer would make. House tools earn their place in the base by being documented or demonstrably consumed.
- **Fix:** keep it; make it legible. Document it in AGENTS.md (what it provides, the parent-fallback behavior, that derivative heroes consume it), and ideally have one base template demonstrate consumption (e.g., an optional hero image in `page-header.twig`). The API-shape critique in PAT-4 still applies.

### YAG-4 · LOW · Dead context keys: `slug`, `post_type`, front-page `posts`
- **Where:** `page.php:12` (`slug` — the template *name* already encodes it), `search.php:15-18` (`post_type` from `$_GET`, used by nothing), `front-page.php:10` (`posts` — `front-page.twig` renders only `post.content`)
- **Fix:** delete all three. `search.php`'s `$_GET` handling is properly sanitized but is speculative plumbing for a filter UI that doesn't exist.

### YAG-5 · LOW · `Vite::img()` has no callers
- **Where:** `lib/Vite.lib.php:150-158`; no `src/img/` directory exists, nothing calls it (README documents it)
- **Fix:** delete (restore trivially when a site needs it), or keep only if README/AGENTS.md promote it as the sanctioned image-asset path — currently AGENTS.md doesn't mention it.

### YAG-6 · LOW · Seven post formats registered, zero templates support them
- **Where:** `lib/Tatami.lib.php:112-123`
- **Issue:** `post-formats` support adds admin UI clutter for a feature no view differentiates; post formats are effectively legacy WP.
- **Fix:** delete the block.

### YAG-7 · LOW · `--typography-body-max-width: none` matches nothing
- **Where:** `src/css/tailwind.css:11`
- **Issue:** no such variable exists in `@tailwindcss/typography` (verified by grep of the installed plugin) — the declaration is a no-op. The presumed intent (unclamp `.prose` width) isn't happening; `.prose` still caps at 65ch.
- **Fix:** delete the line; if unbounded prose is wanted, use `max-w-none` on prose containers or a real plugin override.

---

## 6. Documentation ↔ code drift

*Weighted heavily because AGENTS.md is the operating contract for LLMs building derivative sites — drift here propagates into every future project.*

### DOC-1 · HIGH · AGENTS.md documents an architecture that doesn't exist
- **Where:** AGENTS.md Architecture/tasks sections vs. repo: `lib/Queries.lib.php` (`TatamiQueries`) — **doesn't exist**; `views/macros/` — **doesn't exist**; `views/modules/` — **doesn't exist**; referenced example templates (`page-{slug}.twig` resolution is real, but the macro/module how-tos point at empty air)
- **Why it matters:** an LLM told "reusable queries live as static methods on `TatamiQueries`" will either fail or improvise a file that doesn't match future scaffolding. The doc reads as a map of the repo and it isn't.
- **Field evidence:** the architecture itself is proven — johnson-miller has all three (`lib/Queries.lib.php` with documented static methods, 5 macros, 4 modules) exactly as AGENTS.md describes. The doc describes the *derivative* shape; only the base lacks the scaffolding to make the instructions executable from a fresh copy.
- **Fix:** either scaffold the three (an empty `TatamiQueries` class + `views/macros/.gitkeep` + `views/modules/.gitkeep` with a stub example each — cheap and makes the doc true; johnson-miller's `Queries.lib.php` header comment is a good stub source), or rewrite those sections as "create on first use" instructions with exact boilerplate.
- **Scaffolding candidate — the house image macro.** `views/macros/image.twig` with `acf_image` is common practice (johnson-miller has it; AGENTS.md's macro examples already reference it by name) and is the natural first occupant of `views/macros/`. Three notes on the current version before it becomes base canon:
  1. `Image(image_id)` is **deprecated in Timber v2** (verified in vendor: fires `Helper::deprecated`, alternative `get_image()`) — same class of issue as COR-8/COR-3, v1 idiom surviving the migration. Use `get_image(image_id)`.
  2. No `width`/`height` attributes → layout shift on every image (Core Web Vitals); Timber exposes both.
  3. `alt`/`class`/`sizes` interpolate unescaped while autoescape is off (SEC-2) — media-library alt text containing a quote breaks out of the attribute. Enabling autoescape (SEC-2's fix) covers this; until then this macro is part of that exposure.

  Suggested base version:
  ```twig
  {% macro acf_image(image_id, sizes, class, loading = 'lazy') %}
    {% set img = get_image(image_id) %}
    {% if img %}
      <img
        src="{{ img.src }}"
        srcset="{{ img.srcset }}"
        sizes="{{ sizes|default('100vw') }}"
        width="{{ img.width }}"
        height="{{ img.height }}"
        alt="{{ img.alt }}"
        class="{{ class }}"
        loading="{{ loading }}"
        decoding="async" />
    {% endif %}
  {% endmacro %}
  ```
  (`loading` defaults lazy; pass `'eager'` for above-the-fold heroes. `img.alt` empty → `alt=""`, which is the correct decorative-image behavior.)

### DOC-2 · MEDIUM · AGENTS.md says commit `build/`; `.gitignore` forbids it
- **Where:** AGENTS.md "Before committing" ("The `build/` directory and `acf-json/` should be committed for deployment") vs. `.gitignore` (`/build/`)
- **Why it matters:** direct contradiction in deployment strategy; combined with SEC-5, a deploy that trusts the doc ships a WSOD.
- **Fix:** pick one strategy (build-on-deploy vs. committed artifacts) and align both files.

### DOC-3 · MEDIUM · AGENTS.md security claims are false in code
- **Where:** covered in SEC-1 (SVG "only for admin users") and SEC-2 (auto-escaping); listed here because the *doc statements* need fixing alongside the code.

### DOC-4 · LOW · Version drift across doc/meta files
- **Where:** AGENTS.md says "Vite 5" — `package.json` has `vite ^8.1.3`; README says "WordPress 5.0+" — AGENTS.md says 6.x; `style.css` says `Version: 5.1.0` — `package.json` says `6.0.0`
- **Fix:** align; treat `style.css` as the canonical theme version (it's what WP displays).

### DOC-5 · LOW · README documents dead/unused APIs
- **Where:** README "Custom Twig Filters" (points at the unhooked `add_to_twig()` — COR-5), Vite section advertising `img()` (YAG-5), file list naming `views/partials/menu.twig - Menu component with dropdowns` (orphaned, YAG-2)
- **Fix:** update alongside whichever way those findings are resolved.

---

## 7. i18n

### I18N-1 · MEDIUM · No internationalization foundation
- **Where:** theme-wide — no `load_theme_textdomain()`; `style.css` has no `Text Domain:` header; hardcoded English in every router (`404.php:13` "Oops! Page not found.", `archive.php:13` "Archive", `index.php:17` "Blog", `search.php:13`, `author.php:16`) and template ("No posts found.", "Return to Home", "Previous"/"Next"/"Page … of …", `title="Open submenu"`); `base.twig:13` calls `_e('Skip to content')` with **no text domain** (the one gettext call in the theme, and it's incomplete — also prefer `{{ __('…', 'tatami') }}` over echo-style `_e` inside Twig)
- **Why it matters:** ULM builds for Ontario clients — AODA jurisdiction is also a bilingual (EN/FR) jurisdiction; retrofitting gettext across derivative sites is far more expensive than shipping the base right. Severity medium rather than high only because current client base may be EN-only.
- **Fix:** add the `Text Domain: tatami` header + `load_theme_textdomain('tatami')` in `theme_supports()`; wrap the ~12 existing strings; make "all user-facing strings through `__()`/`{{ __() }}` with the `tatami` domain" an AGENTS.md rule.

---

## 8. Performance & misc

### MISC-1 · LOW · `get_fields('option')` runs uncached on every request
- **Where:** `lib/Tatami.lib.php:91`
- **Issue:** loads every ACF options field (all `get_option` calls + formatting) on every page including 404s — the exact per-request cost AGENTS.md warns about for queries in `add_to_context()`. Acceptable now; degrades as derivative sites grow their options pages.
- **Fix:** acceptable to keep; cheap improvement is caching in a static property per-request (it's called once per request anyway) or documenting that options-page bloat has global cost. No action strictly required — flagging for awareness.

### MISC-2 · LOW · `body { overflow-x: hidden }` masks layout bugs
- **Where:** `src/css/tailwind.css:18-20`
- **Issue:** global horizontal clipping hides the very overflow bugs the fluid grid is designed to prevent, and can interfere with `position: sticky` inside transformed ancestors. Common pragma, but in a *base* theme it institutionalizes "hide the bug."
- **Fix:** consider removing so overflow is visible during development, or keep with a comment explaining it's a final safety net.

### MISC-3 · LOW · Dev-server origin written to `build/hot` may not match the actual port
- **Where:** `vite.config.js` `configureServer` — computes `port || 5173` from *config*, before the server binds
- **Issue:** if 5173 is occupied, Vite auto-increments to 5174 but the hot file still says 5173 → assets 404 in dev.
- **Fix:** write the file in `server.httpServer.once('listening', …)` using the resolved address (or set `strictPort: true`).

### MISC-4 · LOW · `{{ _e(…) }}` relies on Twig's non-yield output mode
- **Where:** `views/base.twig:13`
- **Issue:** `_e` echoes directly; this lands in the right place only because Twig 3.x still defaults to output buffering. Twig 4 makes `use_yield` mandatory, which would relocate the echoed text outside the template flow.
- **Fix:** covered by I18N-1's switch to `{{ __('Skip to content', 'tatami') }}` — return-based, future-proof.

---

## 9. Proposed guardrails (AGENTS.md additions)

Per our discussion: contrast, focus, motion, and content-level WCAG can't be audited in a colorless base — so the base should carry **rails** that keep LLMs compliant when they add the site-specific layer. Proposed additions to AGENTS.md (draft, to review together):

**New "Accessibility requirements" bullets:**
- **Contrast is a token-time decision:** when defining `@theme` brand colors, every foreground/background pairing the design will use must meet WCAG AA (4.5:1 text, 3:1 large text/UI components). Record the intended pairings as comments next to the tokens. Never introduce a text-on-brand combination without checking it. *(This codifies existing house practice — johnson-miller's tokens already do it: `--color-gold-ink: #806326; /* gold for small text on light backgrounds — AA needs 4.5:1, forcing this dark */`. The rail makes it mandatory rather than dependent on whoever wrote that file.)*
- **Focus visibility:** never remove focus outlines without a replacement; every interactive element must have a visible `:focus-visible` state with ≥3:1 contrast against its surroundings.
- **Motion:** all non-essential animation must be wrapped in `motion-safe:` (Tailwind) or `@media (prefers-reduced-motion: no-preference)`. No autoplaying movement > 5s without a pause control.
- **State is programmatic, not just visual:** any UI state shown by color/style (current nav item, selected tab, open accordion, current page) must also be expressed in ARIA (`aria-current`, `aria-expanded`, `aria-selected`).
- **Images:** every `<img>` gets `alt` — from the media library alt field for content images, `alt=""` for decorative; inline decorative SVGs get `aria-hidden="true" focusable="false"`.
- **Forms:** every control gets a programmatic `<label>` (not placeholder-as-label); errors are announced (`aria-describedby` + `role="alert"` or live region).
- **Touch targets:** interactive targets ≥ 24×24 CSS px (WCAG 2.2 AA is coming; 44×44 preferred for primary mobile controls).
- **Language:** user-facing strings go through `__()`/`{{ __('…', 'tatami') }}`; if the site is bilingual, no concatenated sentence-building in templates.

**New "Definition of done" checklist (catches this audit's Critical class):**
Before calling any template work complete, load and eyeball: front page, blog home *with more than one page of posts* (pagination must render), a category archive, a search with results and with none, an author page, a password-protected post/page, and a 404 — with `WP_DEBUG` on. Run one keyboard-only pass: skip link, full nav including submenus, focus visible throughout.

**New doc-integrity rule:**
AGENTS.md must describe the repo as it is. If a change makes a statement in AGENTS.md false (files, behavior, versions, security posture), updating AGENTS.md is part of the change.

**New backport rule (the pattern this audit surfaced):**
The derivative comparison showed base-theme defects being fixed *downstream* and never flowing home: johnson-miller corrected the skip-link target, added `.screen-reader-text`, restructured the type scale into `@theme`, added reduced-motion handling — while the base still ships all four defects to the next project. Propose adding to AGENTS.md's "Extending for a new site" section: **when site work reveals a fix that isn't site-specific (a11y helpers, Timber API corrections, structural CSS, security gating), flag it for backport to the base theme before the project wraps.** Even just a `BACKPORT.md` list per site would have caught most of this audit's High findings years earlier.

---

## 10. What's clean (for the record)

- No direct DB queries, no `echo` in routers, no jQuery, no `tailwind.config.js`, no Sass — the "Things to avoid" list is fully respected.
- `$_GET` input in `search.php` is properly sanitized (`sanitize_key`).
- Lockfiles (`pnpm-lock.yaml`, `composer.lock`) committed; dependencies current (Timber 2.5.1, Tailwind 4.3, Vite 8); recent security-patch commit visible in history.
- Vite ↔ WP bridge architecture is sound (hot-file pattern, manifest reading, module type handling) apart from the failure modes noted.
- `.fluid-grid` implementation is careful (safe-area insets, named lines) and matches its documentation.
- `.editorconfig`, ESLint 9 flat config, and Prettier (with Twig + Tailwind plugins) are coherent; `.prettierignore` for `base.twig` is correctly documented in AGENTS.md.

---

## Summary table (triage order)

| # | ID | Sev | File | Finding |
|---|----|-----|------|---------|
| 1 | COR-1 | Critical | index/archive/search.twig:20 | `{{ posts.pagination }}` fatals every paginated listing page (live in johnson-miller search) |
| 2 | COR-2 | High | home.php:11 | Blog home renders blank — `home.twig` missing, no fallback |
| 3 | COR-3 | High | author.php:14 | Author archives fatal on publicly reachable URLs — recommend disabling the route |
| 4 | SEC-1 | High | Tatami.lib.php:49 | SVG uploads for all roles, unsanitized — contradicts AGENTS.md |
| 5 | SEC-2 | High | theme-wide | Twig autoescape OFF while AGENTS.md claims auto-escaping (misbelief already in derivative code) |
| 6 | A11Y-1 | High | base.twig:12 | Skip link targets nonexistent `#content` (WCAG 2.4.1) — fix exists downstream, backport |
| 7 | DOC-1 | High | AGENTS.md | Documents `TatamiQueries`, `macros/`, `modules/` — proven pattern, missing scaffolding |
| 8 | SEC-3 | Medium | page.php | Password-protected pages silently render full content (one-line fix) |
| 9 | COR-5 | Medium | Tatami.lib.php:137 | `add_to_twig()` never hooked — documented extension point is dead |
| 10 | COR-6 | Medium | base.twig:10 | `wp_body_open()` missing |
| 11 | COR-7 | Medium | base.twig:42 | `wp_footer` inside overridable block — one override kills all scripts |
| 12 | SEC-4 | Medium | Vite.lib.php:41 | Stale `build/hot` points site at dead localhost dev server |
| 13 | SEC-5 | Medium | Vite.lib.php:51 | Missing manifest → uncaught exception → front-end WSOD |
| 14 | A11Y-2 | Medium | base.twig:12 / tailwind.css | `.skip-link`/`.screen-reader-text` undefined — backport johnson-miller's block |
| 15 | A11Y-3 | Medium | partials/menu.twig | Nav pattern inaccessible — dissolves if YAG-2 deletion is taken |
| 16 | A11Y-5 | Medium | partials/pagination.twig:55 | Current page lacks `aria-current="page"` |
| 17 | PAT-1 | Medium | 5 routers | Redundant `Timber::get_posts()` — v2 context already provides `posts` |
| 18 | PAT-2 | Medium | index/archive/search.twig | Identical post loop duplicated ×3 — violates own extraction rule |
| 19 | PAT-3 | Medium | single.php:23 | Template order shadows `single-{slug}.twig` |
| 20 | PAT-4 | Medium | Tatami.lib.php:61 | `setup_featured_image()`: awkward by-ref API, outputs unused |
| 21 | PAT-12 | Medium | tailwind.css | No base heading styles — `<h1>` renders as body text; AGENTS.md claims otherwise |
| 22 | PAT-13 | Medium | tailwind.css:73-90 | Type scale in `:root`/`@layer base` instead of `@theme` — adopt derivative's structure |
| 23 | YAG-1 | Medium | partials/pagination.twig | 145-line partial orphaned (it's also the COR-1 fix) |
| 24 | YAG-2 | Medium | partials/menu.twig | Unused in base *and* derivative — delete; keep a semantic nav skeleton instead |
| 25 | DOC-2 | Medium | AGENTS.md / .gitignore | "Commit `build/`" vs. `/build/` ignored — deployment contradiction |
| 26 | DOC-3 | Medium | AGENTS.md | Security claims false in code (tracks SEC-1, SEC-2) |
| 27 | I18N-1 | Medium | theme-wide | No text domain, no `load_theme_textdomain`, ~12 hardcoded strings |
| 28 | COR-4 | Low | single.php:21 | Password branch renders nonexistent `single-password.twig` (folds into SEC-3 fix) |
| 29 | COR-8 | Low | 3 templates | Deprecated `{{ post.preview }}` → `{{ post.excerpt }}` |
| 30 | SEC-6 | Low | partials/menu.twig:9 | `target` without `rel="noopener"` |
| 31 | SEC-7 | Low | Vite.lib.php:166 | Script tag rebuilt from scratch — drops nonces/attributes |
| 32 | SEC-8 | Low | AGENTS.md | XML-RPC/comments hardening doctrine unimplemented |
| 33 | A11Y-4 | Low | home.php | Blog home sets no title → no `<h1>` (folds into COR-2 fix) |
| 34 | A11Y-6 | Low | 3 templates | Dates not in `<time>` elements |
| 35 | A11Y-7 | Low | menu/pagination.twig | `viewbox` → `viewBox` (5 occurrences; propagated to derivative) |
| 36 | A11Y-8 | Low | base.twig:20 | Redundant `role="main"` |
| 37 | PAT-5 | Low | archive.php:24 | Category template key uses numeric ID, not slug |
| 38 | PAT-6 | Low | 7 PHP files | Tabs vs. mandated 4-space indent |
| 39 | PAT-7 | Low | Theme/Vite classes | Global single-word class names — collision risk |
| 40 | PAT-8 | Low | 404.php / index.php | Stale Timber-starter boilerplate headers |
| 41 | PAT-9 | Low | Tatami.lib.php:16 | Inline-closure hook breaks named-method convention |
| 42 | PAT-10 | Low | Theme.lib.php:27 | CSS enqueued `media: screen` — print gets nothing |
| 43 | PAT-11 | Low | Tatami.lib.php:125 | Redundant `add_theme_support('menus')` |
| 44 | YAG-3 | Low | Tatami.lib.php:61 | Featured-image house tool illegible in base — document/demonstrate (reframed) |
| 45 | YAG-4 | Low | page/search/front-page.php | Dead context keys (`slug`, `post_type`, unused `posts`) |
| 46 | YAG-5 | Low | Vite.lib.php:150 | `Vite::img()` has no callers |
| 47 | YAG-6 | Low | Tatami.lib.php:112 | Post formats registered, unsupported by any template |
| 48 | YAG-7 | Low | tailwind.css:11 | `--typography-body-max-width` is a no-op |
| 49 | DOC-4 | Low | multiple | Version drift (Vite 5 vs 8, WP 5.0 vs 6.x, 5.1.0 vs 6.0.0) |
| 50 | DOC-5 | Low | README.md | Documents dead/unused APIs |
| 51 | MISC-1 | Low | Tatami.lib.php:91 | Uncached `get_fields('option')` every request |
| 52 | MISC-2 | Low | tailwind.css:18 | Global `overflow-x: hidden` masks layout bugs |
| 53 | MISC-3 | Low | vite.config.js | Hot file may record wrong port on auto-increment |
| 54 | MISC-4 | Low | base.twig:13 | `_e()` echo pattern breaks under Twig 4 yield mode |
