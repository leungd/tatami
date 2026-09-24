# AGENTS.md — Tatami WordPress Theme

## Identity

Tatami is a WordPress starter theme. Each site built on it is a **derivative** — the base stays lean, and site-specific code is added per project. Never add site-specific content (brand colors, client copy, hardcoded slugs) to the base theme.

**Upstream is pull-down only.** A derivative pulls base updates from `leungd/tatami`; it never pushes, opens a pull request, or otherwise writes to that repo. The only upstream write allowed from a derivative is a GitHub issue (see "Issue tracker") for a bug or fix that belongs in the base. Base changes are made in the base checkout by its maintainer — a derivative's `upstream` remote has its push URL disabled for this reason.

## Stack

| Layer | Tool | Notes |
|-------|------|-------|
| CMS | WordPress 6.x | Classic theme (not FSE/block theme) |
| Templating | Timber v2 / Twig | PHP files are thin routers; all markup lives in `views/` |
| CSS | Tailwind CSS v4 | CSS-first config in `src/css/tailwind.css` — no `tailwind.config.js` |
| Build | Vite 8 | Dev server with HMR, manifest-based production builds |
| Fields | ACF Pro | Optional dependency — theme must work without it |
| SEO | Yoast SEO | Required at launch by house policy (house ADR); owns head SEO output and the JSON-LD graph. Optional at runtime — the theme works without it |
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
lib/Schema.lib.php     → Tatami\Schema — extends Yoast's schema graph (pure core + WordPress adapter); base plumbing
lib/Attribution.lib.php → Tatami\Attribution — a blog post's public credit (Firm / Written by / Reviewed by); base plumbing
lib/SocialProfiles.lib.php → Tatami\SocialProfiles — the Firm's social profiles from Yoast Site representation (pure); base plumbing
views/                 → All Twig templates
  base.twig            → Root HTML shell — all page templates extend this
  partials/            → Reusable fragments (head, hero, pagination, post-list)
  macros/              → Twig macros for repeated patterns (images, addresses)
  modules/             → Self-contained content sections (FAQs, services grid, map, etc.)
src/css/tailwind.css   → Tailwind config + custom utilities + component styles
src/js/main.js         → JS entry point — imports CSS, initializes modules
tests/run.php          → Standalone PHP test runner for pure helpers (loads tests/test-*.php)
```

### Data flow

1. WordPress routes request → PHP template file (e.g., `page.php`)
2. PHP file builds Timber context, calls `Timber::render('page.twig', $context)`
3. Twig template extends `base.twig`, overrides blocks with page-specific content
4. Global context (menu, site, ACF options, social profiles) injected via `add_to_context()` in `Site.lib.php`

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
- **Sparse, not decorative.** Never add a running play-by-play that narrates your reasoning or restates what a line plainly does (`// Import styles` above an import). Section dividers (`// ===== Modules =====`) are fine as top-level structure in the entry files `src/js/main.js` and `src/css/tailwind.css`, where they map the file; do not use them inside functions or in short modules, and never as a substitute for splitting a file that has outgrown one.
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

Every page's hero renders from `partials/hero.twig`, pulled in by `{% block hero %}` in `base.twig`. **Never hand-roll a `<header>` in a `single-*` / `page-*` template** — override the block and reuse the partial. The partial exposes named blocks so derivatives *extend* the shell instead of duplicating it:

- `heroMedia` — the optional full-bleed featured image
- `heroBody` — the title region (defaults to `<p>{{ title }}</p>`)
- `heroClasses` / `heroBodyClasses` — extra classes on the `<header>` and the body wrapper, for heroes that overlay the body on the media (e.g. `row-start-1` on both regions for a full-bleed video hero)

```twig
{% block hero %}
  {% embed 'partials/hero.twig' with { title, featured_image } %}
    {% block heroBody %}
      {{ parent() }}
      <p class="mt-4 text-sm">
        <time datetime="{{ post.date('c') }}">
          {{ __('Published %s', 'tatami')|format(post.date('F j, Y')) }}
        </time>
      </p>
    {% endblock %}
  {% endembed %}
{% endblock %}
```

`views/single.twig` is the working reference: on the `post` type it adds the published date, plus an updated date when `post.modified_date` falls on a different calendar day, each as `<time datetime>` (ISO 8601) wrapping a `__()`/`|format` label.

**Page structure.** `base.twig` wraps the hero and content in `<{{tag}}>`, defaulting to `<div>`. `single.php` sets `tag` to `article`, so every singular it routes — posts and every custom post type — renders inside `<article>`; `page.php` never sets it, so pages stay `<div>`. `footer.twig`, included by `{% block footer %}`, is the site's `<footer>` landmark — sites fill in its placed wrapper.

**Exactly one `<h1>` per page, on the semantically-primary heading.** The hero title is a label (`<p>`) whenever the `<h1>` lives elsewhere; it is promoted to the `<h1>` only when the title is itself the whole heading (e.g. a person's name, or a utility/listing page). Each page's single `<h1>` is the derivative's responsibility. Two clarifications:

- The `<h1>` may come from the WYSIWYG body (`post.content`) **or a dedicated ACF heading field**. Structured CPT singles have no WYSIWYG `<h1>` — they render an ACF field as the body `<h1>` and keep the hero title as the `<p>` label (reference: the LK site's `single-service.twig`, where the `intro_statement` ACF field is the body `<h1>`).
- **Heading level is semantic, not visual.** A `<p class="text-6xl">` name above an `<h1 class="text-5xl">` keyword line is correct markup. Do not equate "biggest text" with "the `<h1>`."

Why it matters: SEO is core business for this shop — the content team writes one keyword-rich `<h1>` per page ("Dynamic advocate and advisor in high-profile public interest and public law matters"), distinct from the short page title. Promoting the short title to `<h1>` starves the keyword heading.

On content-less listing/utility pages (`archive`, `search`, `404`, the blog `index`) the router-set `title` *is* the whole heading — those base templates override `heroBody` to render `<h1>{{ title }}</h1>` (a working reference for the promotion case). Content pages keep the `<p>` label and get their `<h1>` from the body.

`pnpm lint` enforces a **floor only**: it fails any page template that hand-rolls a `<header>`, including a per-section `<article><header>` — push that markup into a module (modules never extend `base.twig`); page-level meta belongs in `heroBody`. The guardrail is blind to `<h1>` placement — a template that inverts the heading rule still passes lint; that check happens in review (see "Definition of done").

### Related Posts (house tool)

**Related Posts** are the blog posts a host page (a Service, a Lawyer, any post type) is allowed to show, chosen by the categories the host *subscribes to*. The host owns the rule; a post only carries its categories and never declares which pages it belongs to, so one post can appear on several hosts. This is not a "latest posts" list — an unconfigured host shows nothing, never a fallback to recent posts.

The base ships the query; each site adds the field and the section markup.

**Field (per site).** An ACF `taxonomy` field named `related_categories` on every host post type, with `save_terms: 0` — the host stores category IDs as its own meta and is never tagged *into* the category, so it stays out of category archives and counts. Never attach `category` to a CPT's `taxonomies` for this purpose. Recipe (`acf-json/group_<site>_related_posts.json`):

```json
{
  "key": "group_<site>_related_posts",
  "title": "Related Posts",
  "fields": [
    {
      "key": "field_<site>_rp_related_categories",
      "label": "Related Post Categories",
      "name": "related_categories",
      "type": "taxonomy",
      "instructions": "Posts in these categories appear as Related Posts on this page. Leave empty to show none.",
      "taxonomy": "category",
      "field_type": "checkbox",
      "add_term": 0,
      "save_terms": 0,
      "load_terms": 0,
      "return_format": "id"
    }
  ],
  "location": [[{ "param": "post_type", "operator": "==", "value": "service" }]]
}
```

Add one location group per host post type. The field *name* is fixed — `Tatami\Queries::related_posts()` reads it.

**Router.** One line in the host's singular router; the method short-circuits when nothing is subscribed and when ACF is absent:

```php
$context['related_posts'] = Tatami\Queries::related_posts( $post );        // 3 posts
$context['related_posts'] = Tatami\Queries::related_posts( $post, 5 );     // or more
```

**Module (per site).** `modules/related-posts.twig`, guarded with `{% if related_posts is not empty %}` (the result is a Timber collection, which is truthy even when empty — `is not empty` is the guard, not `{% if related_posts %}`). The markup is the site's design; `partials/post-list.twig` is the reference consumer of a post list.

**Migrating an older derivative** that attached `category` to its Service/Lawyer CPTs (Meridian): add the field, copy each host's current category assignments into `related_categories` with a one-off script *before* removing `category` from the CPT's `taxonomies` — dropping the taxonomy first orphans the assignments — then switch the router to `related_posts()`.

### Schema (house tool)

Yoast SEO owns SEO output (house ADR): titles, meta descriptions, canonical, Open Graph, and the single JSON-LD graph. **The theme never prints JSON-LD, microdata, or head meta.** `Tatami\Schema` (`lib/Schema.lib.php`) adds the site's facts to Yoast's graph through the `wpseo_schema_graph` filter — never a second graph.

- **Facts come from house-named ACF fields.** The field names below are fixed because `Tatami\Schema` reads them; keys stay site-prefixed.
- **No-ops cleanly.** Without Yoast the filter is never registered; without ACF the only change is a post's Attribution, which falls back to the Firm. Empty fields leave Yoast's graph unchanged, and a graph with no `#organization` piece (a site represented as a Person in Yoast) is returned as-is.
- **Firm → Organization.** With the Firm fields filled, Yoast's Organization piece gains the Firm-type subtype on its `@type` (`legal` → `LegalService`, `accounting` → `AccountingService`, `financial` → `FinancialService`, anything else → `ProfessionalService`), a structured `PostalAddress`, `telephone`, `faxNumber`, and `email`.
- **Offices.** The Address above is the main office. Each additional Office (`offices` repeater — empty on a single-office site) becomes its own piece with the Firm's `@type`, a stable `@id` (`<home>/#/schema/office/<slug of name>`, or the 1-based row number when unnamed — renaming an Office changes its `@id`), `name`, a structured `address`, `telephone`, `faxNumber`, `email`, and `parentOrganization` → the Firm. The Organization lists them in row order as `department`. Office pieces are appended after Yoast's, leaving Yoast's order intact.
- **Area served.** `area_served` rows become the Organization's `areaServed`, a plain list of place names in row order. One list for the whole Firm — every Service inherits it; empty adds nothing.
- **Post types.** The base assumes the post type names `professional` and `service` (the URL rewrite slug is independent — e.g. `'rewrite' => [ 'slug' => 'lawyers' ]`). New sites register the CPTs under these house names. An older derivative with legacy names maps them with one line in `Site.lib.php`, and everything in the base that needs either name reads it from `Tatami\Schema::post_types()`:

  ```php
  add_filter( 'tatami/schema/post_types', fn( $types ) => [ 'professional' => 'lawyer' ] + $types );
  ```

  A legacy services post type (`services`, `practice-area`) is mapped the same way: `[ 'service' => 'practice-area' ]`, or both keys in one array.
- **Professional → Person.** On a Professional single, a Person piece is appended after Yoast's (and any Office pieces) with a stable `@id` (`<profile URL>#person`), `name`, `url`, `worksFor` → the Firm, `jobTitle` (`job_title`), `sameAs` (`profile_links`), `knowsAbout` (the published `services`, each as a Service with `@id` `<service URL>#service`, `name`, `url`), and `image` → Yoast's `#primaryimage` (the featured image) when the graph has one. Empty fields omit their properties. The Person is the page's main entity: Yoast's WebPage piece gains `mainEntity` → the Person. Yoast's schema page type for Professionals is set to **Profile page** in Yoast's settings (Content types → the Professional post type → Schema) — a Launch checklist item, not code.
- **Service → Service.** On a Service single, a Service piece is appended after Yoast's (and any Office pieces) with a stable `@id` (`<service URL>#service`), `name`, `url`, `description` from the post's manual excerpt (omitted when it has none — WordPress's auto-excerpt of the body is not used), `provider` → the Firm, and `areaServed` = the Firm's Area served list (omitted when empty). The Service is the page's main entity: Yoast's WebPage piece gains `mainEntity` → the Service. Professionals link to Services through `knowsAbout` with the same `@id`, never the reverse — no new fields.
- **FAQs.** On any singular with `faqs` rows, Yoast's WebPage piece gains `FAQPage` and one Question per row in `mainEntity` — see "FAQs (house tool)".
- **Attribution.** On a blog post the Article's `author` follows the post's Attribution (the Firm's Organization or the Professional's Person), `reviewedBy` marks a reviewing Professional, and Yoast's user-derived Person is removed — see "Attribution (house tool)".
- **The pure core is the test seam.** `Tatami\Schema::extend( array $graph, array $facts ): array` takes Yoast's graph plus plain facts and returns the graph, with no WordPress/ACF/Yoast calls; the adapter only gathers facts. Test it through `tests/` (see "Build & dev workflow"), asserting on the returned graph.

**Firm fields (per site).** Add these to the site's Site Settings group (`acf-json/group_<site>_site_settings.json`, see "Options page"):

```json
{
  "key": "group_<site>_site_settings",
  "title": "Site Settings",
  "fields": [
    {
      "key": "field_<site>_ss_firm_type",
      "label": "Firm Type",
      "name": "firm_type",
      "type": "select",
      "instructions": "Tells search engines what kind of firm this is.",
      "choices": {
        "legal": "Law firm (LegalService)",
        "accounting": "Accounting firm (AccountingService)",
        "financial": "Financial firm (FinancialService)",
        "professional": "Other professional firm (ProfessionalService)"
      },
      "default_value": "professional",
      "return_format": "value"
    },
    {
      "key": "field_<site>_ss_address",
      "label": "Address",
      "name": "address",
      "type": "group",
      "instructions": "The main office. Enter it exactly as the Google Business Profile shows it.",
      "layout": "block",
      "sub_fields": [
        {
          "key": "field_<site>_ss_street_address",
          "label": "Street Address",
          "name": "street_address",
          "type": "text",
          "instructions": "Include the suite or unit, as Google Business Profile writes it."
        },
        { "key": "field_<site>_ss_city", "label": "City", "name": "city", "type": "text" },
        { "key": "field_<site>_ss_province", "label": "Province", "name": "province", "type": "text" },
        { "key": "field_<site>_ss_postal_code", "label": "Postal Code", "name": "postal_code", "type": "text" },
        {
          "key": "field_<site>_ss_country",
          "label": "Country",
          "name": "country",
          "type": "text",
          "default_value": "Canada"
        }
      ]
    },
    { "key": "field_<site>_ss_phone_number", "label": "Phone Number", "name": "phone_number", "type": "text" },
    { "key": "field_<site>_ss_fax_number", "label": "Fax Number", "name": "fax_number", "type": "text" },
    { "key": "field_<site>_ss_email_address", "label": "Email Address", "name": "email_address", "type": "email" },
    {
      "key": "field_<site>_ss_offices",
      "label": "Additional Offices",
      "name": "offices",
      "type": "repeater",
      "instructions": "Only for firms with more than one office — leave empty on a single-office site. The main office is the Address above.",
      "layout": "block",
      "button_label": "Add Office",
      "sub_fields": [
        { "key": "field_<site>_ss_office_name", "label": "Name", "name": "name", "type": "text", "required": 1 },
        {
          "key": "field_<site>_ss_office_address",
          "label": "Address",
          "name": "address",
          "type": "group",
          "instructions": "Enter it exactly as the office's Google Business Profile shows it.",
          "layout": "block",
          "sub_fields": [
            {
              "key": "field_<site>_ss_office_street_address",
              "label": "Street Address",
              "name": "street_address",
              "type": "text",
              "instructions": "Include the suite or unit, as Google Business Profile writes it."
            },
            { "key": "field_<site>_ss_office_city", "label": "City", "name": "city", "type": "text" },
            { "key": "field_<site>_ss_office_province", "label": "Province", "name": "province", "type": "text" },
            { "key": "field_<site>_ss_office_postal_code", "label": "Postal Code", "name": "postal_code", "type": "text" },
            {
              "key": "field_<site>_ss_office_country",
              "label": "Country",
              "name": "country",
              "type": "text",
              "default_value": "Canada"
            }
          ]
        },
        { "key": "field_<site>_ss_office_phone_number", "label": "Phone Number", "name": "phone_number", "type": "text" },
        { "key": "field_<site>_ss_office_fax_number", "label": "Fax Number", "name": "fax_number", "type": "text" },
        { "key": "field_<site>_ss_office_email_address", "label": "Email Address", "name": "email_address", "type": "email" }
      ]
    },
    {
      "key": "field_<site>_ss_area_served",
      "label": "Area Served",
      "name": "area_served",
      "type": "repeater",
      "instructions": "Regions the firm takes work from, beyond the office cities — e.g. Eastern Ontario. One list for the whole firm; every Service inherits it.",
      "layout": "table",
      "button_label": "Add Region",
      "sub_fields": [
        { "key": "field_<site>_ss_area_name", "label": "Name", "name": "name", "type": "text", "required": 1 }
      ]
    }
  ],
  "location": [[{ "param": "options_page", "operator": "==", "value": "site-settings" }]]
}
```

**Migrating an older derivative's office fields.** Field names are never renamed on a live site (see "ACF fields"), so:

- **Office repeater named `locations`:** add the `offices` field, copy each `locations` row into it with a one-off script, then drop `locations` from the group JSON and switch the templates to `offices`.
- **Address as a single textarea:** add the `address` group and split the text into its parts — street (including the suite), city, province, postal code — exactly as the Google Business Profile shows them, then retire the textarea from the group JSON and templates.

**Professional fields (per site).** `acf-json/group_<site>_professional.json`. A legacy site changes the location value and the relationship's `post_type` to its own post type names.

```json
{
  "key": "group_<site>_professional",
  "title": "Professional",
  "fields": [
    {
      "key": "field_<site>_pro_job_title",
      "label": "Job Title",
      "name": "job_title",
      "type": "text",
      "instructions": "Shown in schema as the Person's job title, e.g. Partner, Associate."
    },
    {
      "key": "field_<site>_pro_profile_links",
      "label": "Profile Links",
      "name": "profile_links",
      "type": "repeater",
      "instructions": "This person's listings elsewhere, which confirm who they are to search engines. Priorities: the regulator's directory listing (e.g. the Law Society directory), then LinkedIn.",
      "layout": "table",
      "button_label": "Add Link",
      "sub_fields": [
        { "key": "field_<site>_pro_profile_link_url", "label": "URL", "name": "url", "type": "url", "required": 1 }
      ]
    },
    {
      "key": "field_<site>_pro_services",
      "label": "Services",
      "name": "services",
      "type": "relationship",
      "instructions": "Services this Professional practises — listed on their Person as knowsAbout.",
      "post_type": ["service"],
      "filters": ["search"],
      "return_format": "id"
    }
  ],
  "location": [[{ "param": "post_type", "operator": "==", "value": "professional" }]]
}
```

### Address (house tool)

`macros/address.twig` renders an `address` group in the Canadian format Google Business Profile uses, so the visible address matches the listing and the schema: single-line `100 King St W Suite 5600, Toronto, ON M5X 1C9`, or with `multiline` the street, `<br>`, then `Toronto, ON M5X 1C9`. Empty parts are skipped, country is not displayed (it exists for schema), and it emits no wrapper element — the caller places it.

```twig
{% from 'macros/address.twig' import address %}
<address class="not-italic">{{ address(options.address, true) }}</address>
```

### Social profiles (house tool)

The Firm's social profiles are entered in one place: **Yoast SEO → Settings → Site representation** (the Facebook and X fields plus "Other profiles"). Yoast already emits them as the Organization's `sameAs`; templates read the same list from `{{ social_profiles }}` (global context). Never add an ACF repeater for social links.

Each item is `{ network, url }`. `network` is inferred from the URL host by `Tatami\SocialProfiles::network()` — `facebook`, `x`, `instagram`, `linkedin`, `youtube`, `tiktok`, `threads`, `bluesky`, `pinterest`, `wikipedia`, `avvo`, or `link` for any other host — so the site's `macros/icon.twig` needs one glyph per network it uses plus a generic `link` glyph. The X field stores a handle; the list carries the built `https://x.com/<handle>` URL. Order follows Yoast (Facebook, X, then Other profiles); duplicate URLs are listed once. Without Yoast the list is empty.

```twig
{% from 'macros/icon.twig' import icon %}
{% if social_profiles is not empty %}
  <ul class="flex gap-4">
    {% for profile in social_profiles %}
      <li>
        <a href="{{ profile.url }}" target="_blank" rel="noopener noreferrer">
          {{ icon(profile.network) }}
          <span class="sr-only">{{ profile.network|capitalize }}</span>
        </a>
      </li>
    {% endfor %}
  </ul>
{% endif %}
```

**Migrating a site with an ACF `social_media_services` / `social_links` field:** enter each URL in Yoast → Site representation (Facebook, X handle, the rest under Other profiles), switch the footer loop to `social_profiles`, then delete the field from its group JSON in `acf-json/`.

### FAQs (house tool)

One standard for FAQs on any page or post type: a `faqs` repeater, a reference module that renders it as native disclosures, and FAQPage schema built from the same rows.

**Field (per site).** `acf-json/group_<site>_faqs.json`. Add one location group per post type that carries FAQs. The field *name* is fixed — `Tatami\Schema` reads it. Sub-fields are `required` — the in-row exception, since a question without an answer is meaningless.

```json
{
  "key": "group_<site>_faqs",
  "title": "FAQs",
  "fields": [
    {
      "key": "field_<site>_faq_faqs",
      "label": "FAQs",
      "name": "faqs",
      "type": "repeater",
      "instructions": "Each question shows on the page as an expandable question with its answer, and is included in the page's structured data for search engines and AI tools. Leave empty for no FAQ section.",
      "layout": "block",
      "button_label": "Add Question",
      "sub_fields": [
        { "key": "field_<site>_faq_question", "label": "Question", "name": "question", "type": "text", "required": 1 },
        {
          "key": "field_<site>_faq_answer",
          "label": "Answer",
          "name": "answer",
          "type": "wysiwyg",
          "toolbar": "basic",
          "media_upload": 0,
          "required": 1
        }
      ]
    }
  ],
  "location": [[{ "param": "post_type", "operator": "==", "value": "page" }]]
}
```

**Module.** `modules/faqs.twig` renders a `<section>` with a "Frequently Asked Questions" `<h2>` and one `<details>`/`<summary>` per row — the question autoescaped in the `<summary>`, the answer `|raw` (basic-toolbar WYSIWYG). Answers are in the HTML, keyboard-operable and readable with JavaScript off; no script is involved. It uses the house repeater guard (`{% if faqs is iterable and faqs is not empty %}`), so it renders nothing with no rows or with ACF deactivated. `page.twig` and `single.twig` already include it in `{% block modules %}`; a derivative styles it, or overrides the block to place it elsewhere:

```twig
{% include 'modules/faqs.twig' with { faqs: post.meta('faqs') } %}
```

**Schema.** On any singular with FAQ rows, `Tatami\Schema` adds `FAQPage` to Yoast's WebPage `@type` and sets `mainEntity` to one `Question` (`name`) with an `acceptedAnswer` `Answer` (`text`, the answer HTML) per row, in row order — built from the same rows the module renders, so the graph matches the visible text. On a Professional or Service page the Person or Service reference stays first in `mainEntity`, followed by the Questions. No rows adds nothing.

FAQ rich results have been restricted to authoritative government and health sites since 2023, so law and financial firms won't get one; the value here is AI-readable Q&A, on the page and in the graph.

**Migrating an older derivative** (Hicks Adams, McCay Duff, Getz Collins, Meridian) off in-template JSON-LD and microdata FAQs: add the field and move each page's Q&A into the `faqs` repeater (McCay Duff's block-based FAQs need a one-off content migration script), delete the hand-rolled `<script type="application/ld+json">` / `itemscope` FAQ markup and any FAQ schema class, and include the module.

### Attribution (house tool)

A blog post's **Attribution** is the credit it publicly carries: the Firm itself, "Written by" a Professional, or "Reviewed by" a Professional — never the WordPress user who entered the post. Every public credit surface (the visible byline, Yoast's author meta, the share card, the schema graph) derives from one resolver, `Tatami\Attribution` (`lib/Attribution.lib.php`).

**States.** `firm` (the default), `written_by`, `reviewed_by`.

**Resolver.** `Tatami\Attribution::resolve( $post_id )` returns `{ state, label, name, url }`:

- `firm` → label "Written on behalf of", name the site title (Settings → General), url `null`.
- `written_by` / `reviewed_by` → label "Written by" / "Reviewed by", name the Professional's current title, url the Professional's profile permalink.
- A personal credit needs a **published Professional** (`attribution_person`, of the Professional post type from `Tatami\Schema::post_types()`). No Professional, a draft/trashed one, or an unknown state → the Firm. Without ACF every post credits the Firm.

`single.php` puts the result in the `attribution` context key on posts, labels already translated. The base's `single.twig` renders a minimal credit in `heroBody` after the dates; the markup is per site:

```twig
{% if attribution %}
  <p class="text-sm">
    <span>{{ attribution.label }}</span>
    {% if attribution.url %}
      <a href="{{ attribution.url }}">{{ attribution.name }}</a>
    {% else %}
      {{ attribution.name }}
    {% endif %}
  </p>
{% endif %}
```

**Yoast.** `<meta name="author">` and the share card's "Written by" row (`twitter:label1`/`twitter:data1`) carry the resolved label and name. The WordPress Author box is hidden in the admin and removed from the REST API (where the block editor reads it) so there is no second, wrong place to assign credit; post author support stays on the front end because Yoast skips Article schema for a post type without it.

**Schema.** Through `Tatami\Schema`, per state:

- `firm` → the Article's `author` (and the WebPage's, when Yoast sets one) → the Organization.
- `written_by` → `author` → the Professional's Person.
- `reviewed_by` → `author` → the Organization; the WebPage gains `reviewedBy` → the Professional's Person.
- A credited Professional is appended as a minimal Person (`name`, `url`) whose `@id` is `<profile URL>#person` — the same `@id` as the Person on that Professional's profile page, so author and profile are one Entity (the full description lives on the profile page).
- In every state Yoast's user-derived Person (`…/#/schema/person/<hash>`) is removed, so no WordPress user or author-archive URL appears in a post's graph.

**Field (per site).** `acf-json/group_<site>_attribution.json`. The field *names* are fixed — `Tatami\Attribution` reads them. `attribution_name` is written from the Professional's title on save so editors see the credit in the list and edit screens; output always uses the Professional's current title. A legacy site changes the person field's `post_type` to its own Professional post type.

```json
{
  "key": "group_<site>_attribution",
  "title": "Attribution",
  "position": "acf_after_title",
  "fields": [
    {
      "key": "field_<site>_attr_state",
      "label": "Attribution",
      "name": "attribution_state",
      "type": "radio",
      "instructions": "The one public credit this post carries. It drives the byline, the share-card author, and structured data.",
      "default_value": "firm",
      "choices": {
        "firm": "Written on behalf of the firm",
        "written_by": "Written by a Professional",
        "reviewed_by": "Reviewed by a Professional"
      }
    },
    {
      "key": "field_<site>_attr_person",
      "label": "Credited Professional",
      "name": "attribution_person",
      "type": "post_object",
      "instructions": "The byline links to their profile. If they are unpublished later, the post credits the firm.",
      "post_type": ["professional"],
      "post_status": ["publish"],
      "allow_null": 1,
      "return_format": "id",
      "conditional_logic": [[{ "field": "field_<site>_attr_state", "operator": "!=", "value": "firm" }]]
    },
    {
      "key": "field_<site>_attr_name",
      "label": "Credited Name",
      "name": "attribution_name",
      "type": "text",
      "instructions": "Auto-filled from the credited Professional on save.",
      "conditional_logic": [[{ "field": "field_<site>_attr_state", "operator": "!=", "value": "firm" }]]
    }
  ],
  "location": [[{ "param": "post_type", "operator": "==", "value": "post" }]]
}
```

**Migrating Boulby Weinberg and Willis Business Law (new)** off their own attribution class:

1. Delete the site's attribution class (its `require_once` and instantiation) and its `wpseo_meta_author`, `wpseo_enhanced_slack_data`, and `wpseo_schema_graph` filters — the base registers the first two and `Tatami\Schema` owns the graph.
2. Keep the existing field group and its keys; confirm the three field *names* are `attribution_state`, `attribution_person`, `attribution_name` (they are on Willis). Change the person field's `post_type` if needed.
3. If the Professional post type is legacy (`lawyer`), add the `tatami/schema/post_types` line in `Site.lib.php` (see "Schema").
4. Point the byline templates at the `attribution` context key.

The credited Person's `@id` changes from a hash of the name to the profile's `<profile URL>#person` — a one-time change in the graph, intended. A credit typed as a name alone, for someone without a published profile, now falls back to the Firm.

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
2. Import in templates: `{% from 'macros/image.twig' import image %}`
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
- `{{ social_profiles }}` — list of `{ network, url }` from Yoast → Site representation (primary profiles, then Other profiles); `network` inferred from the host, `link` for unknown hosts; empty without Yoast (see "Social profiles")

**Keep `add_to_context()` lean.** Only put data here that is truly needed on every page (menu, site, options, social profiles). Page-specific queries belong in the PHP router file for that page, and the query itself lives in `Tatami\Queries` (see "Add a reusable query" below):
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

### ACF fields (house conventions)

**Plugin.** ACF Pro, installed from advancedcustomfields.com — never Secure Custom Fields (the wp.org fork: a diverging codebase, not a drop-in, and it deactivates ACF Pro on activation), never both. ACF stays an optional dependency (see PHP rules): PHP guards plus template truthiness let a site render, degraded, without it.

**`acf-json/` is the sole author.** Field groups are hand-authored JSON files in `acf-json/` (committed), loaded by ACF automatically — no PHP registration, no `save_json`/`load_json` filters, and the admin field-group editor is never used to create, edit, or sync a group. A group synced into the DB makes the admin editor show a copy that the JSON silently overrides at runtime — if one appears, delete the DB copy. Author the files minimal: 2-space per `.editorconfig`, only the settings that matter (ACF fills defaults at load), no `modified` timestamp — it exists to drive the sync UI this workflow forbids.

**Keys and names.** Group keys are `group_<site>_<slug>`; field keys are `field_<site>_<group-abbrev>_<name>` (`field_lk_fp_hero_heading`, `fp` = front page). Keys are a global namespace within a WP install — unique across the theme, every plugin, and ACF's own auto-keys — so the site prefix is mandatory. Field *names* stay unprefixed and human-readable (`hero_heading`): names are what templates read, and a deliberately shared name lets one module consume the same shape from different groups (two `testimonials` repeaters on different post types, one module). Never rename a live field's key **or** name — values sit in postmeta under the name with a paired `_`-row referencing the key, so either rename orphans content. The prefix rule is going-forward; existing unprefixed sites are grandfathered.

**Return formats.** Media and relational fields return **IDs** (`return_format: id` on image, gallery, post_object, relationship), hydrated at the point of use: the house `image()` macro takes an ID directly; `get_image(id)`, `get_post(id)` / `get_posts(ids)` cover the rest. IDs keep every image on the house renderer and dodge a known Timber v2 rough edge with array-format images inside nested structures. `link` fields return `array` (`url`/`title`/`target`) — no Timber wrapper exists for them. Textareas store plain text (`new_lines: ""`) and render with `|nl2br` — never `wpautop`, which forces `|raw` onto a plain-text field. WYSIWYG fields use the `basic` toolbar with `media_upload: 0`, and are the only per-post fields rendered with `|raw` (see Security).

**Modeling.** Fixed fields per template, organized with `tab` fields, matching the design's sections. Flexible content only when a site genuinely needs editor-arranged sections (layouts map to `modules/{layout}.twig`); ACF Blocks are out of scope for this classic theme. Repeaters are for bounded, order-matters lists owned by one page (testimonials, offices, social links); anything queryable, listable, or unbounded is a CPT, and filterable groupings are a taxonomy (ACF fields on terms are fine — read them via `get_term_meta()` where ACF-optional code needs the value). Never nest repeaters. `show_in_rest: 0` unless a group deliberately feeds the REST API. No `required` fields — templates guard on truthiness and sections no-op when empty, the same contract modules follow — except sub-fields inside a repeater row, where a half-filled row is meaningless. Use `instructions` to tell editors what the template will do (fallbacks, image-count expectations, "this is the page's H1").

**Accented headings.** When a design accents words inside a heading (italic serif, highlight bar, brand color), the accent is `<em>`: the heading field is a basic-toolbar WYSIWYG and the theme styles `em` within that heading's scope. Editors italicize the word — no hand-authored spans, no split accent/rest fields; the styling degrades to plain italics without CSS.

**Editability.** Editors own the message; the theme owns the interface. Field anything the client could plausibly ask to reword without a redesign — headings, body copy, blurbs, images, link targets (test: could marketing change this on a Tuesday without a designer looking at it?). Hardcode, through `__()`, anything that is interface rather than message: section order, UI microcopy (expand/collapse labels, "Read More", form labels, pagination), empty-state text — changing those is a design change and goes through code. Data-driven sections render from their sources (CPT/post queries); only their intro blurbs are fields — no curation fields ("pick which items appear") until a real need exists.

**Options page.** One per site, and only when the site has global settings. Register it in `Site.lib.php` (site surface) on `acf/init`, guarded; define its fields in `group_<site>_site_settings.json` with an `options_page == site-settings` location; consume as `{{ options.x }}` from the global context. Keep the page lean — it loads on every request (see "Add global context"). `'autoload' => true` folds the option rows into WP's autoload query instead of one query per field:

```php
public function register_options_page(): void {
    if ( ! function_exists( 'acf_add_options_page' ) ) {
        return;
    }
    acf_add_options_page( array(
        'page_title' => __( 'Site Settings', 'tatami' ),
        'menu_title' => __( 'Site Settings', 'tatami' ),
        'menu_slug'  => 'site-settings',
        'capability' => 'manage_options',
        'redirect'   => false,
        'autoload'   => true,
    ) );
}
```

**Reading fields.** Twig reads `post.meta('name')`; routers never call `get_field()` — per-post ACF data reaches templates through the Timber post object, options through `{{ options.x }}`. Call `meta()` once on a repeater or group field and use rows as plain properties (`item.title`) — never nest `meta()` on sub-fields. Shared modules guard repeaters with `{% if items is iterable and items is not empty %}`: with ACF deactivated, `post.meta()` on a repeater returns the raw row count, not rows. ACF 6.2.5+'s `the_field()`/shortcode escaping change is a non-event here (Timber themes use neither); never add `acf/…/allow_unsafe_html` filters.

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

The underlying distinction: `Vite.lib.php` + `Assets.lib.php` + `Schema.lib.php` + `Attribution.lib.php` + `SocialProfiles.lib.php` are **base plumbing** — keep them pristine so they diff clean against the base. `Site.lib.php` + `Queries.lib.php` are the **site surface** — extend them freely (CPTs, taxonomies, hooks, queries, and hosted-font enqueues all live here).

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
- Access ACF fields via: `{{ post.meta('field_name') }}` or `{{ options.field_name }}` (see "ACF fields (house conventions)")
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
- ACF fields that accept HTML (WYSIWYG) should use `| raw` — plain text fields must not; multi-line plain text renders via `|nl2br`, never `wpautop` + `|raw` (see "ACF fields (house conventions)").
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
pnpm test             # Node linter tests, then PHP tests of the pure helpers (php tests/run.php)
pnpm format           # Prettier (JS, CSS, Twig)
```

`tests/run.php` runs with plain `php` — no WordPress, no PHPUnit — and exits without output unless run from the CLI. It loads the pure libs under test, then every `tests/test-*.php`, and exits 1 on any failure. Shared helpers: `assert_equal()`, `assert_true()`, and `yoast_graph_fixture( 'post' | 'front' | 'professional' | 'service' )`, a realistic Yoast 22+ graph.

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
- **No hand-written JSON-LD, microdata, or head meta** — Yoast owns SEO output; extend its graph via `Tatami\Schema`
- **No hand-rolled page headers** — a `single-*`/`page-*` template must not contain its own `<header>`; override `{% block hero %}` and reuse `partials/hero.twig` (enforced by `pnpm lint`)

## Definition of done (template work)

Before calling any template work complete, load and eyeball — with `WP_DEBUG` on: the front page, the blog home *with more than one page of posts* (pagination must render), a category archive, a search with results and with none, and a 404. Run one keyboard-only pass: skip link, full nav including any submenus, focus visible throughout.

Confirm exactly one `<h1>` per page, on the keyword/primary heading, with the page title as a `<p>` label unless the title is the whole heading (see "Hero"). This check lives in review, not CI — the lint guardrail only catches hand-rolled `<header>`s and is blind to `<h1>` placement; it will pass a template that inverts the heading rule.

Check the schema on the front page, a Professional, a Service, a post (one in each Attribution state the site uses), and a page with FAQs:

- The page carries exactly one JSON-LD graph — Yoast's `<script type="application/ld+json" class="yoast-schema-graph">` — and no other `application/ld+json` block or `itemscope` microdata (see "Schema").
- Paste the graph into the [Rich Results Test](https://search.google.com/test/rich-results) or the [Schema Markup Validator](https://validator.schema.org/); it passes.

With JavaScript off (view-source or `curl`), the key content is in the page source: headings, body copy, FAQ answers, the Professional's name and job title, the address. Nothing that matters is injected by script.

Confirm AI search/retrieval bots are not blocked, in `robots.txt` (Yoast generates it) or at the host/CDN (e.g. Cloudflare's "Block AI bots" toggle).

## Launch checklist

Set these on production before launch; each says where it lives.

- [ ] **Yoast SEO is active** (Plugins). House policy — without it the base outputs no schema.
- [ ] **Site representation** (Yoast SEO → Settings → Site representation): Organization, not Person; organisation name; logo; social profiles (Facebook, X handle, Other profiles). The same list feeds `{{ social_profiles }}` and the Organization's `sameAs` (see "Social profiles").
- [ ] **Author archives off** (Yoast SEO → Settings → Advanced → Author archives). The theme already 404s them; this also drops them from the sitemaps and Yoast's graph.
- [ ] **Professional page type = Profile page** (Yoast SEO → Settings → Content types → the Professional post type → Schema → Page type).
- [ ] **llms.txt enabled** (Yoast SEO → Settings → Site features → llms.txt) and regenerated on production once content is final. Never ship a locally generated file.
- [ ] **Search/retrieval AI bots allowed**: `robots.txt` has no `Disallow` for them, and the host/CDN does not block them (Cloudflare → Security → Bots → "Block AI bots" off, or an allow rule for the retrieval agents). Training bots (GPTBot, ClaudeBot, Google-Extended, CCBot, …) are allowed by default unless the client decides otherwise — record the client's decision.
- [ ] **Firm fields filled** (Site Settings): Firm type; the main Office address exactly as the Google Business Profile shows it; phone, fax, email; Offices only if there is more than one; Area served (see "Schema").
- [ ] **Schema checks run** — the Definition-of-done schema checks, on the pages listed there.

### Migrating an older derivative

Adopt the house tools in this order; each step's details live in that tool's own migration note:

1. Declare legacy post types with the `tatami/schema/post_types` filter — "Schema (house tool)", **Post types**.
2. Adopt the house field names: Firm fields on Site Settings, splitting a single-box address and copying `locations` into `offices` — "Schema (house tool)", **Migrating an older derivative's office fields**.
3. Enter the social profiles in Yoast and retire the ACF repeater — "Social profiles (house tool)".
4. Replace hand-rolled FAQ JSON-LD/microdata with the `faqs` field and module — "FAQs (house tool)".
5. Move Boulby/Willis-style attribution onto `Tatami\Attribution` — "Attribution (house tool)".
6. Run the Launch checklist.

## Doc integrity

AGENTS.md must describe the repo as it is. If a change makes a statement in AGENTS.md false (files, behavior, versions, security posture), updating AGENTS.md is part of the change.

## Extending for a new site

When building a new site on Tatami:
1. Clone the base theme into the new project, then rename the remote and disable its push URL so the derivative can only pull:
   ```bash
   git remote rename origin upstream
   git remote set-url --push upstream DISABLED
   ```
2. Define brand colors and fonts in `src/css/tailwind.css` `@theme` block
3. Register custom post types and taxonomies in `lib/Site.lib.php`
4. Set up ACF field groups per "ACF fields (house conventions)": hand-authored minimal JSON in `acf-json/` (committed), site-prefixed keys, ID return formats, no admin-UI authoring.
5. Copy the Firm field recipe (see "Schema (house tool)") into the site's Site Settings group, with the site's key prefix
6. Build page templates in `views/` following the naming conventions above
7. Extract reusable sections into `views/modules/` and `views/partials/`
8. Add JS interactivity in `src/js/main.js` using the module pattern
9. Add reusable queries to `Tatami\Queries` (`lib/Queries.lib.php`), then call them from the appropriate router file and assign to context

## Agent skills

The base repo carries no `docs/` directory, `CONTEXT.md`, or ADRs. Agent skill config and decision records for base development live outside the checkout in the maintainer's working folder, so a derivative never pulls them down.

### Issue tracker

Issues are tracked as GitHub issues in `leungd/tatami` via the `gh` CLI; external PRs are not a triage surface.

### Triage labels

Canonical triage roles map 1:1 to their default label strings (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`).
