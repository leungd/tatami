# Hero (house tool)

Every page's hero renders from `partials/hero.twig`, pulled in by `{% block hero %}` in `base.twig`, unless the page skips it under the rules in "Skipping the hero" below. **Never hand-roll a `<header>` in a `single-*` / `page-*` template** — override the block and reuse the partial. The partial exposes named blocks so derivatives *extend* the shell instead of duplicating it:

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

## Other layouts

The partial is a frame, not a look: a `<header class="fluid-grid">` with slots. For a page top that isn't image + title (e.g. an intro beside a map), override `heroMedia` to nothing and lay the columns out inside `heroBody`, text first, so the reading order matches the visual order when the columns stack.

## Skipping the hero

A page whose top section genuinely doesn't fit the frame may skip it. Try the frame first; skipping is the exception. The template:

- empties `{% block hero %}` — a page template extends `base.twig` directly rather than `page.twig`, since the hero is what the Page shell is for — and builds the section in `{% block content %}` as a `<div class="fluid-grid">`, never a `<header>`;
- still carries exactly one `<h1>`;
- keeps a one-line `{# … #}` note saying the section sits outside the hero by design, so nobody moves it back into the partial.

## Featured images

Routers for singular views assign `$context['featured_image'] = Tatami\Queries::featured_image_with_fallback($post);` — a Timber image object with parent fallback (a page without a thumbnail inherits its parent's). Templates read `featured_image.src`, `featured_image.alt`, `featured_image.width`, `featured_image.height`; `partials/hero.twig` consumes it as the optional `heroMedia`. Derivative heroes build on this key.

## Page structure

`base.twig` wraps the hero and content in `<{{tag}}>`, defaulting to `<div>`. `single.php` sets `tag` to `article`, so every singular it routes — posts and every custom post type — renders inside `<article>`; `page.php` never sets it, so pages stay `<div>`. `footer.twig`, included by `{% block footer %}`, is the site's `<footer>` landmark — sites fill in its placed wrapper.

## One `<h1>` per page

**Exactly one `<h1>` per page, on the semantically-primary heading.** The hero title is a label (`<p>`) whenever the `<h1>` lives elsewhere; it is promoted to the `<h1>` only when the title is itself the whole heading (e.g. a person's name, or a Listing page). Each page's single `<h1>` is the derivative's responsibility. Two clarifications:

- The `<h1>` may come from the WYSIWYG body (`post.content`) **or a dedicated ACF heading field**. Structured CPT singles have no WYSIWYG `<h1>` — they render an ACF field (an intro statement, a keyword line) as the body `<h1>` and keep the hero title as the `<p>` label.
- **Heading level is semantic, not visual.** A `<p class="text-6xl">` name above an `<h1 class="text-5xl">` keyword line is correct markup. Do not equate "biggest text" with "the `<h1>`."

Why it matters: SEO is core business for this shop — the content team writes one keyword-rich `<h1>` per page ("Dynamic advocate and advisor in high-profile public interest and public law matters"), distinct from the short page title. Promoting the short title to `<h1>` starves the keyword heading.

On Listing pages (`archive`, `search`, `404`, the blog `index`) the router-set `title` *is* the whole heading — those base templates override `heroBody` to render `<h1>{{ title }}</h1>` (a working reference for the promotion case). Content pages keep the `<p>` label and get their `<h1>` from the body.

`pnpm lint` enforces a **floor only**: it fails any page template that hand-rolls a `<header>`, including a per-section `<article><header>` — a section's heading group in a page template is a `<div>`, and page-level meta belongs in `heroBody`. Modules never extend a template, so they are outside the guard, but that is not a reason to extract a single-use section (see "Add a reusable module" in AGENTS.md). The guardrail is blind to `<h1>` placement — a template that inverts the heading rule still passes lint; that check happens in review (see "Definition of done" in AGENTS.md).
