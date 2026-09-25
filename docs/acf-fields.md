# ACF fields (house conventions)

**Plugin.** ACF Pro, installed from advancedcustomfields.com — never Secure Custom Fields (the wp.org fork: a diverging codebase, not a drop-in, and it deactivates ACF Pro on activation), never both. ACF stays an optional dependency (see PHP rules in AGENTS.md): PHP guards plus template truthiness let a site render, degraded, without it.

**`acf-json/` is the sole author.** Field groups are hand-authored JSON files in `acf-json/` (committed), loaded by ACF automatically — no PHP registration, no `save_json`/`load_json` filters, and the admin field-group editor is never used to create, edit, or sync a group. A group synced into the DB makes the admin editor show a copy that the JSON silently overrides at runtime — if one appears, delete the DB copy. Author the files minimal: 2-space per `.editorconfig`, only the settings that matter (ACF fills defaults at load), no `modified` timestamp — it exists to drive the sync UI this workflow forbids.

**Recipes.** The house tools' field groups ship as copy sources in `recipes/acf/`, one file per group, with `SITE` where the site prefix goes. To adopt one: copy the file into `acf-json/`, rename it, and replace every `SITE` with the site prefix (`group_SITE_faqs` → `group_acme_faqs`, `field_SITE_faq_faqs` → `field_acme_faq_faqs`). Field *names* in a recipe are fixed — the base reads them — so change only keys and location rules. ACF never scans `recipes/`, so the templates are inert until copied. `pnpm test` checks that every recipe parses and keeps its placeholder.

**Keys and names.** Group keys are `group_<site>_<slug>`; field keys are `field_<site>_<group-abbrev>_<name>` (`field_acme_fp_hero_heading`, `fp` = front page). Keys are a global namespace within a WP install — unique across the theme, every plugin, and ACF's own auto-keys — so the site prefix is mandatory. Field *names* stay unprefixed and human-readable (`hero_heading`): names are what templates read, and a deliberately shared name lets one module consume the same shape from different groups (two `testimonials` repeaters on different post types, one module). Never rename a live field's key **or** name — values sit in postmeta under the name with a paired `_`-row referencing the key, so either rename orphans content. The prefix rule is going-forward; existing unprefixed sites are grandfathered.

**Return formats.** Media and relational fields return **IDs** (`return_format: id` on image, gallery, post_object, relationship), hydrated at the point of use: the house `image()` macro takes an ID directly; `get_image(id)`, `get_post(id)` / `get_posts(ids)` cover the rest. IDs keep every image on the house renderer and dodge a known Timber v2 rough edge with array-format images inside nested structures. `link` fields return `array` (`url`/`title`/`target`) — no Timber wrapper exists for them. Textareas store plain text (`new_lines: ""`) and render with `|nl2br` — never `wpautop`, which forces `|raw` onto a plain-text field. WYSIWYG fields use the `basic` toolbar with `media_upload: 0`, and are the only per-post fields rendered with `|raw` (see Security in AGENTS.md).

**Modeling.** Fixed fields per template, organized with `tab` fields, matching the design's sections. Flexible content only when a site genuinely needs editor-arranged sections (layouts map to `modules/{layout}.twig`); ACF Blocks are out of scope for this classic theme. Repeaters are for bounded, order-matters lists owned by one page (testimonials, offices, social links); anything queryable, listable, or unbounded is a CPT, and filterable groupings are a taxonomy (ACF fields on terms are fine — read them via `get_term_meta()` where ACF-optional code needs the value). Never nest repeaters. `show_in_rest: 0` unless a group deliberately feeds the REST API. No `required` fields — templates guard on truthiness and sections no-op when empty, the same contract modules follow — except sub-fields inside a repeater row, where a half-filled row is meaningless. Use `instructions` to tell editors what the template will do (fallbacks, image-count expectations, "this is the page's H1").

**Accented headings.** When a design accents words inside a heading (italic serif, highlight bar, brand color), the accent is `<em>`: the heading field is a basic-toolbar WYSIWYG and the theme styles `em` within that heading's scope. Editors italicize the word — no hand-authored spans, no split accent/rest fields; the styling degrades to plain italics without CSS.

**Editability.** Editors own the message; the theme owns the interface. Field anything the client could plausibly ask to reword without a redesign — headings, body copy, blurbs, images, link targets (test: could marketing change this on a Tuesday without a designer looking at it?). Hardcode, through `__()`, anything that is interface rather than message: section order, UI microcopy (expand/collapse labels, "Read More", form labels, pagination), empty-state text — changing those is a design change and goes through code. Data-driven sections render from their sources (CPT/post queries); only their intro blurbs are fields — no curation fields ("pick which items appear") until a real need exists.

**Options page.** One per site, and only when the site has global settings. Register it in `Site.lib.php` (site surface) on `acf/init`, guarded; define its fields in `group_<site>_site_settings.json` with an `options_page == site-settings` location (the Firm fields recipe is the starting point — see `docs/schema.md`); consume as `{{ options.x }}` from the global context. Keep the page lean — it loads on every request (see "Add global context" in AGENTS.md). `'autoload' => true` folds the option rows into WP's autoload query instead of one query per field:

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
