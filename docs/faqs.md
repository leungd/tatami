# FAQs (house tool)

The base fixes two things about an FAQ and leaves the rest to the site. The **field name**, because `Tatami\Schema` builds the page's FAQPage schema from it. The **disclosure pattern**, because answers must be in the HTML, keyboard-operable, and readable with JavaScript off. How FAQs look, and which pages carry them, is the site's design. The base ships no FAQ template or module; a site writes the markup into the template that has FAQs — typically a dedicated FAQ page (`page-faqs.twig`), sometimes a Service single — and extracts its own module only when a second template needs it.

## Field (per site)

A `faqs` repeater of `question` (text) and `answer` (basic-toolbar WYSIWYG), both `required` — the in-row exception, since a question without an answer is meaningless. The field *name* is fixed.

Recipe: `recipes/acf/group_SITE_faqs.json` — copy it into `acf-json/`, replace `SITE` with the site prefix (see `docs/acf-fields.md`), and **narrow the location** to where FAQs live. The recipe's `post_type == page` is a placeholder: point it at the FAQ page (`page` / `page_template`) or the post type that carries per-item questions (`post_type == service`). One location group per placement.

## Markup

One native `<details>`/`<summary>` per row: the question autoescaped in the `<summary>`, the answer `|raw` (it is WYSIWYG), behind the house repeater guard so nothing renders with no rows or with ACF deactivated. No script. The heading, the wrapper, and every class are the site's:

```twig
{% set faqs = post.meta('faqs') %}
{% if faqs is iterable and faqs is not empty %}
  <section aria-labelledby="faqs-heading">
    <h2 id="faqs-heading">{{ __('Frequently Asked Questions', 'tatami') }}</h2>
    {% for faq in faqs %}
      <details>
        <summary>{{ faq.question }}</summary>
        <div class="prose">{{ faq.answer|raw }}</div>
      </details>
    {% endfor %}
  </section>
{% endif %}
```

A custom-scripted accordion is not a substitute: `<details>` gives the open/closed state, keyboard handling, and JavaScript-off fallback natively, and styles freely (`summary::marker`, `details[open]`).

## Schema

On any singular with FAQ rows, `Tatami\Schema` adds `FAQPage` to Yoast's WebPage `@type` and sets `mainEntity` to one `Question` (`name`) with an `acceptedAnswer` `Answer` (`text`, the answer HTML) per row, in row order — built from the same rows the module renders, so the graph matches the visible text. On a Professional or Service page the Person or Service reference stays first in `mainEntity`, followed by the Questions. No rows adds nothing.

FAQ rich results have been restricted to authoritative government and health sites since 2023, so law and financial firms won't get one; the value here is AI-readable Q&A, on the page and in the graph.

Hand-rolled `<script type="application/ld+json">` or `itemscope` FAQ markup in a template is a defect to replace with this field and module, never a pattern to copy.
