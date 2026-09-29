# Attribution (house tool)

A blog post's **Attribution** is the credit it publicly carries: the Firm itself, "Written by" a named person, or "Reviewed by" a named person — usually a Professional, never the WordPress user who entered the post. Every public credit surface (the visible byline, Yoast's author meta, the share card, the feed, oEmbed, the schema graph) derives from one resolver, `Tatami\Attribution` (`lib/Attribution.lib.php`).

## States

`firm` (the default), `written_by`, `reviewed_by`.

## Resolver

`Tatami\Attribution::resolve( $post_id )` returns `{ state, label, name, url }`:

- `firm` → label "Written on behalf of", name the site title (Settings → General), url `null`.
- `written_by` / `reviewed_by` → label "Written by" / "Reviewed by", name the typed Credited Name (`attribution_name`), url the Professional's profile permalink while that Professional (`attribution_person`, of the Professional post type from `Tatami\Schema::post_types()`) is published, otherwise `null`.
- **The typed name is the credit; the Professional is only a link.** Lawyers leave firms, and a post keeps its credit when the profile is unpublished — it just loses the link. A person with no profile (a law clerk, a guest writer) is credited by name alone. To take a credit away, set the post back to `firm`.
- An empty Credited Name falls back to the published Professional's title; with neither, or an unknown state → the Firm. Without ACF every post credits the Firm.

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

The share card's "Written by" row (`twitter:label1`/`twitter:data1`) carries the resolved label and name. `<meta name="author">` carries the author (below). The WordPress Author box is hidden in the admin and removed from the REST API (where the block editor reads it) so there is no second, wrong place to assign credit; post author support stays on the front end because Yoast skips Article schema for a post type without it.

## Author

A surface with one "author" slot names whoever `Tatami\Attribution::author( $attribution, $firm_name, $firm_url )` returns: the credited person for `written_by` (with the profile url, if any), otherwise the Firm (site title, home url). A reviewer is credited as reviewer, never as author, which matches the schema below. Surfaces:

- `<meta name="author">`.
- The feed: `<dc:creator>` (RSS2, RDF) and Atom's `<author><name>`, through `the_author` in feed context; Atom's `<uri>` is the author url, omitted for a name-only credit.
- oEmbed: `author_name`, and `author_url` when there is one. It never points at an author archive. Post types without Attribution fields resolve to the Firm.

## Schema

Through `Tatami\Schema`, per state:

- `firm` → the Article's `author` (and the WebPage's, when Yoast sets one) → the Organization.
- `written_by` → `author` → the credited Person.
- `reviewed_by` → `author` → the Organization; the WebPage gains `reviewedBy` → the credited Person.
- A credit with a published profile is appended as a minimal Person (`name`, `url`) whose `@id` is `<profile URL>#person` — the same `@id` as the Person on that Professional's profile page, so author and profile are one Entity (the full description lives on the profile page).
- A name-only credit is appended as a Person with `name` alone, `@id` `<site>/#/schema/credited-person/<md5 of the lowercased name>`, so the same name on several posts is one Entity.
- In every state Yoast's user-derived Person (`…/#/schema/person/<hash>`) is removed, so no WordPress user or author-archive URL appears in a post's graph.

## Field (per site)

Recipe: `recipes/acf/group_SITE_attribution.json` — copy it into `acf-json/` and replace `SITE` with the site prefix (see `docs/acf-fields.md`). The field *names* are fixed — `Tatami\Attribution` reads them. Picking a Professional writes their title into `attribution_name` on save, so the name costs editors nothing; renaming a Professional updates a post's credit the next time that post is saved. A site with a legacy Professional post type name changes the person field's `post_type` to its own.

