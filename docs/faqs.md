# FAQs (house tool)

One standard for FAQs on any page or post type: a `faqs` repeater, a reference module that renders it as native disclosures, and FAQPage schema built from the same rows.

## Field (per site)

Recipe: `recipes/acf/group_SITE_faqs.json` — copy it into `acf-json/` and replace `SITE` with the site prefix (see `docs/acf-fields.md`). Add one location group per post type that carries FAQs. The field *name* is fixed — `Tatami\Schema` reads it. Sub-fields are `required` — the in-row exception, since a question without an answer is meaningless.

## Module

`modules/faqs.twig` renders a `<section>` with a "Frequently Asked Questions" `<h2>` and one `<details>`/`<summary>` per row — the question autoescaped in the `<summary>`, the answer `|raw` (basic-toolbar WYSIWYG). Answers are in the HTML, keyboard-operable and readable with JavaScript off; no script is involved. It uses the house repeater guard (`{% if faqs is iterable and faqs is not empty %}`), so it renders nothing with no rows or with ACF deactivated. `page.twig` and `single.twig` already include it in `{% block modules %}`; a derivative styles it, or overrides the block to place it elsewhere:

```twig
{% include 'modules/faqs.twig' with { faqs: post.meta('faqs') } %}
```

## Schema

On any singular with FAQ rows, `Tatami\Schema` adds `FAQPage` to Yoast's WebPage `@type` and sets `mainEntity` to one `Question` (`name`) with an `acceptedAnswer` `Answer` (`text`, the answer HTML) per row, in row order — built from the same rows the module renders, so the graph matches the visible text. On a Professional or Service page the Person or Service reference stays first in `mainEntity`, followed by the Questions. No rows adds nothing.

FAQ rich results have been restricted to authoritative government and health sites since 2023, so law and financial firms won't get one; the value here is AI-readable Q&A, on the page and in the graph.

Hand-rolled `<script type="application/ld+json">` or `itemscope` FAQ markup in a template is a defect to replace with this field and module, never a pattern to copy.
