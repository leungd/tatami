# Related Posts (house tool)

**Related Posts** are the blog posts a host page (a Service, a Professional, any post type) is allowed to show, chosen by the categories the host *subscribes to*. The host owns the rule; a post only carries its categories and never declares which pages it belongs to, so one post can appear on several hosts. This is not a "latest posts" list — an unconfigured host shows nothing, never a fallback to recent posts.

The base ships the query; each site adds the field and the section markup.

## Field (per site)

An ACF `taxonomy` field named `related_categories` on every host post type, with `save_terms: 0` — the host stores category IDs as its own meta and is never tagged *into* the category, so it stays out of category archives and counts. Never attach `category` to a CPT's `taxonomies` for this purpose.

Recipe: `recipes/acf/group_SITE_related_posts.json` — copy it into `acf-json/` and replace `SITE` with the site prefix (see `docs/acf-fields.md`). Add one location group per host post type. The field *name* is fixed — `Tatami\Queries::related_posts()` reads it.

## Router

One line in the host's singular router; the method short-circuits when nothing is subscribed and when ACF is absent:

```php
$context['related_posts'] = Tatami\Queries::related_posts( $post );        // 3 posts
$context['related_posts'] = Tatami\Queries::related_posts( $post, 5 );     // or more
```

## Module (per site)

`modules/related-posts.twig`, guarded with `{% if related_posts is not empty %}` (the result is a Timber collection, which is truthy even when empty — `is not empty` is the guard, not `{% if related_posts %}`). The markup is the site's design; `partials/post-list.twig` is the reference consumer of a post list.
