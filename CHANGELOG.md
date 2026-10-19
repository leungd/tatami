# Changelog

One entry per version, newest first. **Upgrade** lists what a derivative does by hand when it pulls that version; a version without it needs nothing. A derivative that skips versions works through every Upgrade list between its version and the new one, oldest first.

## 7.2.1 — 2026-10-19

- Removed the `wp tatami migrate-faqs` command; 7.2.0's FAQ upgrade is done by hand.
- AGENTS.md: "Base changes that sites must adopt" — upgrade steps live here, and migration scripts are the exception.
- Added this changelog, from 6.2.4 on.

## 7.2.0 — 2026-10-19

- FAQs are blocks. Writers insert an **FAQs** block (`tatami/faqs`) and add **Question** blocks (`tatami/faq`): an inline question, and an answer of Paragraph and List blocks. Each question renders as `<details>`/`<summary>` through `views/blocks/faq.twig`; the list through `views/blocks/faqs.twig`. Both ship without classes.
- `Tatami\Schema` builds FAQPage from the Question blocks instead of the `faqs` ACF repeater, so FAQs no longer need ACF. Yoast's FAQ block is hidden from the inserter.
- The `faqs` repeater recipe (`recipes/acf/group_SITE_faqs.json`) is removed. See `docs/faqs.md`.

**Upgrade** — a site that used the `faqs` repeater. The repeater's questions leave the schema as soon as this version is pulled.

1. Re-enter each post's real FAQs as an FAQs block in its content, with a Heading block above if the template supplied one. Placeholder rows can be dropped.
2. Move the site's FAQ design from the template that rendered the repeater into `views/blocks/faq.twig` and `views/blocks/faqs.twig`, then delete the repeater markup. A template that showed FAQs must output `post.content`.
3. Delete `acf-json/group_<site>_faqs.json` and anything that reads it (a site test, a page-setup script).
4. If merging conflicts on `package.json`'s `format` script, keep both sides: the site's own scripts and the base's added `'blocks/**/*.js'`.

## 7.1.1 — 2026-10-18

- `docs/schema.md`: the one-Professional Firm pattern, for a Firm whose About page is its one lawyer's bio.

## 7.1.0 — 2026-10-06

- `page.php` and `home.php` set `hero_title_tag` from the page body: `'p'` when the body carries its own `<h1>`, `'h1'` when it does not.

**Upgrade**

1. A page template whose `<h1>` lives outside the post body (an ACF field, a module) sets `{% set hero_title_tag = 'p' %}`.
2. A Listing page template that set `hero_title_tag` to `'h1'` can drop that line; the router now decides.

## 7.0.0 — 2026-10-05

- The Firm type is set in code with the `tatami/schema/firm_type` filter, not a Site Settings field (unset → ProfessionalService). The `firm_type` select is removed from the Site Settings recipe.
- `views/page.twig` embeds `partials/hero.twig` and exposes one setting, `hero_title_tag`, as a top-level `{% set %}` written `|default(...)`.

**Upgrade**

1. A site that saved a Firm type adds `add_filter( 'tatami/schema/firm_type', fn() => '<type>' );` to `Site.lib.php` (`legal`, `accounting`, `financial` or `professional`) and removes `firm_type` from its `acf-json/` Site Settings group.
2. A site with its own `views/page.twig` keeps its design and embeds the hero the same way: `{% set hero_title_tag = hero_title_tag|default('p') %}` at the top, passed into the hero embed.

## 6.2.4 — 2026-09-30

- Page templates extend `page.twig`, the Page shell, and override only what differs; a page that skips the hero extends `base.twig` directly.
- `pnpm lint`'s hero guard checks every template that extends another, not only those extending `base.twig`.

**Upgrade**

1. Run `pnpm lint`. A page template it now flags for a hand-rolled `<header>` moves that markup into the hero (`docs/hero.md`).
