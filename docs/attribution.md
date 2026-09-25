# Attribution (house tool)

A blog post's **Attribution** is the credit it publicly carries: the Firm itself, "Written by" a Professional, or "Reviewed by" a Professional — never the WordPress user who entered the post. Every public credit surface (the visible byline, Yoast's author meta, the share card, the schema graph) derives from one resolver, `Tatami\Attribution` (`lib/Attribution.lib.php`).

## States

`firm` (the default), `written_by`, `reviewed_by`.

## Resolver

`Tatami\Attribution::resolve( $post_id )` returns `{ state, label, name, url }`:

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

## Yoast

`<meta name="author">` and the share card's "Written by" row (`twitter:label1`/`twitter:data1`) carry the resolved label and name. The WordPress Author box is hidden in the admin and removed from the REST API (where the block editor reads it) so there is no second, wrong place to assign credit; post author support stays on the front end because Yoast skips Article schema for a post type without it.

## Schema

Through `Tatami\Schema`, per state:

- `firm` → the Article's `author` (and the WebPage's, when Yoast sets one) → the Organization.
- `written_by` → `author` → the Professional's Person.
- `reviewed_by` → `author` → the Organization; the WebPage gains `reviewedBy` → the Professional's Person.
- A credited Professional is appended as a minimal Person (`name`, `url`) whose `@id` is `<profile URL>#person` — the same `@id` as the Person on that Professional's profile page, so author and profile are one Entity (the full description lives on the profile page).
- In every state Yoast's user-derived Person (`…/#/schema/person/<hash>`) is removed, so no WordPress user or author-archive URL appears in a post's graph.

## Field (per site)

Recipe: `recipes/acf/group_SITE_attribution.json` — copy it into `acf-json/` and replace `SITE` with the site prefix (see `docs/acf-fields.md`). The field *names* are fixed — `Tatami\Attribution` reads them. `attribution_name` is written from the Professional's title on save so editors see the credit in the list and edit screens; output always uses the Professional's current title. A site with a legacy Professional post type name changes the person field's `post_type` to its own.

A credit typed as a name alone, for someone without a published profile, falls back to the Firm.
