# Enforce the hero-partial pattern

**Date:** 2026-07-10
**Status:** Design approved, pending implementation plan

## Problem

Derivative `single-*` / `page-*` templates tend to hand-roll their own page-header
markup (`<header class="fluid-grid">…`) instead of reusing `partials/hero.twig`.
This duplicates the hero markup across a site and breaks the theme's DRY intent.

Two root causes:

1. **Ergonomics** — the hero partial only accepts `title` + `featured_image`. When a
   page needs anything more (post meta, an eyebrow, a subtitle), the partial can't
   express it, so the LLM abandons it and writes a bespoke `<header>`.
2. **No backstop** — nothing fails when a template hand-rolls a header, so the
   anti-pattern lands silently and is copied forward.

This work coincides with an in-flight rename: `partials/page-header.twig` →
`partials/hero.twig`, and the base block `pageHeader` → `hero`. The rename already
makes AGENTS.md's "Featured images" section false (it still cites `page-header.twig`),
so a doc pass is required regardless.

## Design

Three coordinated changes: make the DRY path the *easy* path, back it with an airtight
lint guardrail, and record the rule in AGENTS.md.

### 1. Make `hero.twig` `embed`-able (ergonomics)

Wrap the two variable regions of the partial in named blocks. Plain `{% include %}`
output stays byte-identical, so `base.twig` and every current template are unaffected.

```twig
{% if title %}
  <header class="fluid-grid">
    {% block heroMedia %}
      {% if featured_image %}
        <img
          class="col-[full-start/full-end] max-h-96 w-full object-cover"
          src="{{ featured_image.src }}"
          alt="{{ featured_image.alt }}"
          width="{{ featured_image.width }}"
          height="{{ featured_image.height }}"
          loading="eager"
        />
      {% endif %}
    {% endblock %}
    <div class="col-[content-start/content-end]">
      {% block heroBody %}<p>{{ title }}</p>{% endblock %}
    </div>
  </header>
{% endif %}
```

Key decisions:

- **The hero title is a label (`<p>`) on content pages, but the page heading (`<h1>`)
  on content-less listing/utility pages.** On `page`/`single`/`front-page` the WYSIWYG
  body carries the real `<h1>`, so the hero title is a label. But `archive`, `search`,
  `404`, and the blog `index` have no `post.content` and their router-set `title`
  (e.g. "Category: …", "Search results for …") *is* the page's heading — leaving it a
  `<p>` would ship those pages headingless (a WCAG 1.3.1/2.4.6 regression against the
  theme's own AA posture). So those four base templates override `heroBody` to render
  `<h1>{{ title }}</h1>`, using the exact embed pattern this work promotes:

  ```twig
  {% block hero %}
    {% embed 'partials/hero.twig' with { title } %}
      {% block heroBody %}<h1>{{ title }}</h1>{% endblock %}
    {% endembed %}
  {% endblock %}
  ```

  The default (`heroBody` = `<p>`) stays label-shaped; the listing/utility templates opt
  into the heading. This makes the base a working reference for both consumption shapes.
- **Plain `<p>{{ title }}</p>`, no class hook.** Lean base; derivatives style the label
  by overriding `heroBody` or via their own CSS.
- **Only the two slots the base actually renders** (`heroMedia`, `heroBody`). No
  speculative eyebrow/meta/actions blocks (YAGNI) — derivatives inject that markup
  *through* `heroBody`.

`base.twig` is unchanged: it still `{% include %}`s the partial with `{ title }`, and
`featured_image` continues to flow in from the surrounding context (the include is not
`only`-scoped).

A derivative now **extends** the shell instead of replacing it:

```twig
{% block hero %}
  {% embed 'partials/hero.twig' with { title, featured_image } %}
    {% block heroBody %}
      {{ parent() }}
      <p class="mt-4 text-sm">{{ post.date|date('F j, Y') }}</p>
    {% endblock %}
  {% endembed %}
{% endblock %}
```

### 2. Lint guardrail — `scripts/lint-templates.mjs`

A dependency-free Node ESM script (matches `"type": "module"`; Node 24 in use, no new
deps — recursive `readdir`, no glob library) wired into `pnpm lint`.

**Single, airtight rule:**

> A `.twig` file that extends `base.twig` must not contain a `<header` token.

The extends match is tolerant of realistic authoring drift — single or double quotes and
Twig whitespace-control (`{%- extends "base.twig" -%}`) — via the regex
`/\{%-?\s*extends\s+['"]base\.twig['"]/`, because the guardrail's real audience is
derivative sites, which is exactly where that drift appears. (Deeper indirection — a page
that extends an intermediate `base-narrow.twig` which extends `base.twig` — is out of
scope; the base ships no such layout.) The `<header` match is a plain substring: this
also, by design, disallows per-section `<article><header>` inside a page template — such
markup belongs in a module (modules never extend base), and page-level meta belongs in
`heroBody`.

Rationale: the hero shell is `<header class="fluid-grid">`. A page template that writes
its own `<header>` is reproducing the hero — exactly the anti-pattern. Card/article
`<header>` elements inside partials and modules (which do **not** extend `base.twig`)
are untouched and remain valid HTML.

The earlier draft also banned `<h1>` outside the hero on a "one h1 = the hero" premise.
That premise is now false — with the title demoted to a `<p>`, the real `<h1>` lives in
the page template's content region, which is where derivatives correctly place it.
Banning `<h1>` there would fight the correct pattern, so **the `<h1>` rule is dropped.**
The guardrail deliberately does *not* try to detect a *missing* h1 (it can arrive via an
included module — too many false positives); the doc carries that responsibility.

**No allowlist needed.** The `{% extends 'base.twig' %}` filter is self-limiting: only
page templates extend base. The site chrome (`views/header.twig`) and the hero itself
(`partials/hero.twig`) are included/embedded, not extended, so they never match the
filter and are never targeted — without an explicit allowlist. Omitting the allowlist is
also stricter: an allowlist entry would *mask* the error if one of those files were ever
wrongly given an `extends` tag. `base.twig` extends nothing, so it is not a target
either.

**Output:** on violation, print `file:line` and the guidance message, then exit `1`:

```
hero-check: views/single-service.twig:4
  hand-rolled <header> — override {% block hero %} and embed partials/hero.twig instead.
```

**Wiring:** `"lint": "eslint src/ && node scripts/lint-templates.mjs"`.

### 3. AGENTS.md updates (doc integrity)

- **Fix both stale `page-header` references:** the architecture file tree (AGENTS.md
  line ~30, `partials/ → … page-header, …`) and the "Featured images (house tool)"
  section (line ~92, `partials/page-header.twig demonstrates consumption…`). Both become
  `hero`.
- **Add a "Hero (house tool)" subsection** under "How to do common tasks" stating:
  - The rule: never hand-roll a `<header>`/page-header in a `single-*`/`page-*`
    template — override `{% block hero %}` and include or **embed**
    `partials/hero.twig`.
  - The `embed` recipe (as above).
  - The a11y contract: **the hero title is a label (`<p>`), not the page heading.** The
    derivative must supply the page's single `<h1>` in its content/module region. In
    practice this is almost always covered by the `<h1>` content writers include in the
    WYSIWYG body (`post.content`); the rule exists so a page without body content still
    gets a heading rather than silently shipping headingless.
- **"Things to avoid":** add one line — no hand-rolled page headers in page templates;
  override `{% block hero %}`.
- **"Build & dev workflow":** note that `pnpm lint` now also runs the template check.

## Out of scope (YAGNI)

- No speculative hero sub-blocks beyond `heroMedia` / `heroBody`.
- No class hook on the base `<p>` label.
- No pre-commit hook (none exists today; `pnpm lint` is the gate).
- No "missing h1" detection in the guardrail.

## Verification

- `pnpm lint` passes clean on the base theme.
- A deliberate `<header>` in a scratch `views/page-test.twig` (extending base) trips the
  guardrail with the expected `file:line` + message; removing it passes.
- `pnpm format` still handles `hero.twig` (it is not in `.prettierignore`, and the new
  named blocks introduce no dynamic-tag or attribute-`{% if %}` pattern).
- The rendered hero HTML for a normal page is unchanged except `<h1>{{ title }}</h1>` →
  `<p>{{ title }}</p>`.

## Repo note

This file is a process artifact. Per AGENTS.md "Doc integrity", tag before pruning it
from HEAD when the work lands (the product-facing rule lives in AGENTS.md, which stays).
