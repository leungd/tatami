# FAQs (house tool)

The base defines the **shape** of an FAQ — the field, the markup, the schema — and nothing else. It does not decide which pages carry one. Nothing in the base includes the module; a site includes it only in the templates that have FAQs, if the sitemap has any at all: typically a dedicated FAQ page (`page-faqs.twig`), sometimes a Service single with its own questions. The same shape serves both, so a site never re-invents FAQ markup or schema per placement.

## Field (per site)

A `faqs` repeater of `question` (text) and `answer` (basic-toolbar WYSIWYG), both `required` — the in-row exception, since a question without an answer is meaningless. The field *name* is fixed — `Tatami\Schema` reads it.

Recipe: `recipes/acf/group_SITE_faqs.json` — copy it into `acf-json/`, replace `SITE` with the site prefix (see `docs/acf-fields.md`), and **narrow the location** to where FAQs live. The recipe's `post_type == page` is a placeholder: point it at the FAQ page (`page` / `page_template`) or the post type that carries per-item questions (`post_type == service`). One location group per placement.

## Module

`modules/faqs.twig` is the reference markup: a self-placing `<section class="fluid-grid">` with a "Frequently Asked Questions" `<h2>` and one `<details>`/`<summary>` per row — the question autoescaped in the `<summary>`, the answer `|raw`. Answers are in the HTML, keyboard-operable and readable with JavaScript off; no script is involved. It uses the house repeater guard (`{% if faqs is iterable and faqs is not empty %}`), so it renders nothing with no rows or with ACF deactivated. A derivative styles it and, if the section heading should differ per placement, passes a `heading` or overrides the module — but keeps `<details>`/`<summary>`, the `|raw` answer, and the guard.

Include it at the top level of `{% block content %}` in the template that carries FAQs — never inside a placed column (it carries its own grid), and not in `{% block modules %}`, which is for site bands:

```twig
{# page-faqs.twig, or single-service.twig #}
{% block content %}
  <div class="fluid-grid">
    <div class="col-[content-start/content-end]">
      <div class="prose">{{ post.content|raw }}</div>
    </div>
  </div>
  {% include 'modules/faqs.twig' with { faqs: post.meta('faqs') } %}
{% endblock %}
```

## Schema

On any singular with FAQ rows, `Tatami\Schema` adds `FAQPage` to Yoast's WebPage `@type` and sets `mainEntity` to one `Question` (`name`) with an `acceptedAnswer` `Answer` (`text`, the answer HTML) per row, in row order — built from the same rows the module renders, so the graph matches the visible text. On a Professional or Service page the Person or Service reference stays first in `mainEntity`, followed by the Questions. No rows adds nothing.

FAQ rich results have been restricted to authoritative government and health sites since 2023, so law and financial firms won't get one; the value here is AI-readable Q&A, on the page and in the graph.

Hand-rolled `<script type="application/ld+json">` or `itemscope` FAQ markup in a template is a defect to replace with this field and module, never a pattern to copy.
