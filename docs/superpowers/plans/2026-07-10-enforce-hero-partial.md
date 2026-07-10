# Enforce the hero-partial pattern — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `partials/hero.twig` the single, `embed`-able source of every page's hero, and stop derivative templates from hand-rolling their own — via an ergonomic partial, a lint guardrail, and updated docs.

**Architecture:** `base.twig` already renders the hero through `{% block hero %}` → `{% include 'partials/hero.twig' %}`. We wrap the partial's two variable regions in named blocks (`heroMedia`, `heroBody`) so derivatives *extend* rather than replace it, demote the title from `<h1>` to a `<p>` label, and add a dependency-free Node check (wired into `pnpm lint`) that fails any page template containing a hand-rolled `<header>`.

**Tech Stack:** Timber v2 / Twig, Node 24 (ESM, built-in `node:test` — no new deps), pnpm, ESLint.

## Global Constraints

- **pnpm only** — never npm or yarn. Lockfile is `pnpm-lock.yaml`.
- **No new dependencies** — the guardrail uses only Node built-ins (`node:fs`, `node:path`, `node:url`, `node:test`).
- **Indentation:** 2 spaces for Twig and JS (per `.editorconfig`).
- **JS is ESM** — `package.json` has `"type": "module"`; use `import`/`export`, `.mjs` for standalone scripts.
- **Lean base** — no site-specific content; no speculative features (only `heroMedia` + `heroBody` blocks, no class hooks).
- **Baseline:** the rename `partials/page-header.twig` → `partials/hero.twig` and block `pageHeader` → `hero` is already in the working tree (untracked `hero.twig`, deleted `page-header.twig`, modified `base.twig`). This plan builds on top of it and folds it into the first commit.
- **Doc integrity:** AGENTS.md must stay true to the repo (AGENTS.md "Doc integrity").

---

## Setup: isolate the work on a branch

- [ ] **Step 1: Create a feature branch**

The repo is currently on `main` with the rename uncommitted in the working tree. `checkout -b` carries those changes onto the new branch.

Run:
```bash
cd /Volumes/Work/themes/tatami
git checkout -b hero-partial-enforcement
git status
```
Expected: on branch `hero-partial-enforcement`; `views/base.twig` modified, `views/partials/page-header.twig` deleted, `views/partials/hero.twig` untracked, plus the two `docs/superpowers/` files.

---

## Task 1: Make `hero.twig` embed-able + demote title to a label

**Files:**
- Modify: `views/partials/hero.twig` (currently untracked — the renamed partial)
- Touches (already correct, do not edit): `views/base.twig` (includes the partial with `{ title }`)

**Interfaces:**
- Produces: `partials/hero.twig` exposing two overridable Twig blocks — `heroMedia` (the optional featured image) and `heroBody` (the title region, default `<p>{{ title }}</p>`). Derivatives consume these via `{% embed 'partials/hero.twig' with { title, featured_image } %}`.

**Note on testing:** the theme has no Twig render-test harness, and adding one is out of scope. Verification for this task is a Prettier stability check plus a rendered-HTML eyeball — not a unit test. Do not invent a test framework.

- [ ] **Step 1: Rewrite the partial with named blocks and a `<p>` label**

Replace the entire contents of `views/partials/hero.twig` with:

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

The only behavioral change is `<h1>{{ title }}</h1>` → `<p>{{ title }}</p>`; the blocks are transparent to a plain `{% include %}`.

- [ ] **Step 2: Confirm Prettier handles the file unchanged**

Run:
```bash
pnpm format
git diff --stat views/partials/hero.twig
```
Expected: `pnpm format` exits 0 with no error. If Prettier reformats `hero.twig`, that reformat is acceptable *only* if it preserves the structure above; if the Twig parser errors on the file, STOP — the file would need adding to `.prettierignore` (it should not; the named blocks use no dynamic-tag or attribute-`{% if %}` pattern).

- [ ] **Step 3: Verify the rendered hero output**

If a local WordPress (Local by Flywheel) is available, load any page with `WP_DEBUG` on and confirm the hero band renders the title inside `<p>…</p>` (not `<h1>`), the featured image still appears when present, and no PHP/Twig notice is logged. If no local site is available, note this step as deferred to the Definition-of-Done pass (AGENTS.md) and continue.

- [ ] **Step 4: Commit (folds in the rename baseline)**

```bash
git add views/base.twig views/partials/hero.twig views/partials/page-header.twig
git commit -m "refactor: make hero partial embed-able, render title as label

Rename page-header.twig -> hero.twig (block pageHeader -> hero) and expose
heroMedia/heroBody blocks so derivatives extend the hero instead of
hand-rolling one. Title is a <p> label, not the page <h1>.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```
Expected: commit succeeds; `git status` shows `page-header.twig` gone, `hero.twig` tracked.

---

## Task 2: Lint guardrail — fail hand-rolled headers in page templates

**Files:**
- Create: `scripts/lint-templates.mjs`
- Create: `scripts/lint-templates.test.mjs`
- Modify: `package.json` (the `scripts` block)

**Interfaces:**
- Produces: `checkTemplate(relPath: string, content: string): { file: string, line: number } | null` — returns the first violation (a `<header` token in a file that contains `{% extends 'base.twig' %}`) or `null`. Also `collectTwigFiles(dir: string): string[]` (recursive `.twig` collector). The module runs a CLI that exits `1` on any violation only when invoked directly.
- Consumes: nothing from earlier tasks.

- [ ] **Step 1: Write the failing tests**

Create `scripts/lint-templates.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { checkTemplate } from './lint-templates.mjs';

test('flags a hand-rolled <header> in a page template', () => {
  const content = [
    "{% extends 'base.twig' %}",
    '{% block hero %}',
    '  <header class="fluid-grid">',
    '    <p>{{ title }}</p>',
    '  </header>',
    '{% endblock %}',
  ].join('\n');
  assert.deepEqual(checkTemplate('views/single-service.twig', content), {
    file: 'views/single-service.twig',
    line: 3,
  });
});

test('passes a page template that overrides hero via embed', () => {
  const content = [
    "{% extends 'base.twig' %}",
    '{% block hero %}',
    "  {% embed 'partials/hero.twig' with { title, featured_image } %}",
    '    {% block heroBody %}{{ parent() }}<p>{{ post.date }}</p>{% endblock %}',
    '  {% endembed %}',
    '{% endblock %}',
  ].join('\n');
  assert.equal(checkTemplate('views/single-service.twig', content), null);
});

test('ignores <header> in files that do not extend base (site chrome, hero partial)', () => {
  const content = '<header>\n  <nav>…</nav>\n</header>';
  assert.equal(checkTemplate('views/header.twig', content), null);
});

test('ignores <header> in a module (modules never extend base)', () => {
  const content = '<article>\n  <header>Card title</header>\n</article>';
  assert.equal(checkTemplate('views/modules/service-card.twig', content), null);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:
```bash
node --test scripts/
```
Expected: FAIL — cannot resolve `./lint-templates.mjs` (module does not exist yet).

- [ ] **Step 3: Write the guardrail script**

Create `scripts/lint-templates.mjs`:

```js
import { readdirSync, readFileSync } from 'node:fs';
import { join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const VIEWS_DIR = 'views';

// A page template extends base.twig. The hero shell (`<header class="fluid-grid">`)
// belongs only in partials/hero.twig, consumed via {% block hero %} in base.twig.
// A page template that writes its own <header> is reproducing the hero — the
// anti-pattern this guard exists to stop. The extends filter is self-limiting:
// the site chrome (header.twig) and the hero partial are included/embedded, never
// extended, so they are never targeted — no allowlist needed.
export function checkTemplate(relPath, content) {
  if (!content.includes("{% extends 'base.twig' %}")) return null;
  const lines = content.split('\n');
  for (let i = 0; i < lines.length; i++) {
    if (lines[i].includes('<header')) {
      return { file: relPath, line: i + 1 };
    }
  }
  return null;
}

export function collectTwigFiles(dir) {
  const out = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...collectTwigFiles(full));
    } else if (entry.name.endsWith('.twig')) {
      out.push(full);
    }
  }
  return out;
}

function main() {
  const violations = [];
  for (const file of collectTwigFiles(VIEWS_DIR)) {
    const rel = file.split(sep).join('/');
    const violation = checkTemplate(rel, readFileSync(file, 'utf8'));
    if (violation) violations.push(violation);
  }
  for (const v of violations) {
    console.error(`hero-check: ${v.file}:${v.line}`);
    console.error(
      '  hand-rolled <header> — override {% block hero %} and embed partials/hero.twig instead.'
    );
  }
  if (violations.length) process.exit(1);
}

// Run the CLI only when invoked directly, not when imported by tests.
if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main();
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run:
```bash
node --test scripts/
```
Expected: PASS — 4 tests, 0 failures.

- [ ] **Step 5: Wire the guardrail and test runner into `package.json`**

In `package.json`, change the `lint` script and add a `test` script. The `scripts` block becomes:

```json
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview",
    "lint": "eslint src/ && node scripts/lint-templates.mjs",
    "test": "node --test scripts/",
    "format": "prettier --write 'src/**/*.{js,css}' 'views/**/*.twig'"
  },
```

- [ ] **Step 6: Verify the guardrail passes clean on the real theme**

Run:
```bash
pnpm lint
```
Expected: ESLint passes, then the hero-check prints nothing and exits 0 (no current template extends base *and* contains `<header>`).

- [ ] **Step 7: Verify the guardrail actually trips on a violation**

Create a throwaway file, run lint, confirm the failure, then delete it (do NOT commit it):

```bash
printf "%s\n" "{% extends 'base.twig' %}" "{% block hero %}" '  <header class="fluid-grid"><p>{{ title }}</p></header>' "{% endblock %}" > views/page-lint-probe.twig
pnpm lint; echo "exit=$?"
rm views/page-lint-probe.twig
```
Expected: output contains `hero-check: views/page-lint-probe.twig:3` and the guidance line; `exit=1`. After `rm`, `git status` shows no stray file.

- [ ] **Step 8: Commit**

```bash
git add scripts/lint-templates.mjs scripts/lint-templates.test.mjs package.json
git commit -m "feat: lint guardrail against hand-rolled page headers

pnpm lint now fails any template that extends base.twig and contains a
<header>, pointing it back to the hero block. Dependency-free (node:test).

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```
Expected: commit succeeds.

---

## Task 3: Update AGENTS.md

**Files:**
- Modify: `AGENTS.md` (four edits: file-tree ref, Featured-images ref, new Hero section, Things-to-avoid + workflow notes)

**Interfaces:** none (documentation only).

- [ ] **Step 1: Fix the stale `page-header` reference in the architecture file tree**

Find (around line 30):
```
  partials/            → Reusable fragments (head, page-header, pagination, post-list)
```
Replace with:
```
  partials/            → Reusable fragments (head, hero, pagination, post-list)
```

- [ ] **Step 2: Fix the stale reference in "Featured images (house tool)"**

Find (around line 92):
```
Templates read `featured_image.src`, `featured_image.alt`, `featured_image.width`, `featured_image.height`; `partials/page-header.twig` demonstrates consumption as an optional hero.
```
Change `partials/page-header.twig` to `partials/hero.twig` in that sentence (leave the rest of the sentence intact).

- [ ] **Step 3: Add the "Hero (house tool)" subsection**

Insert the following block immediately **after** the end of the "### Featured images (house tool)" section and **before** the "### Add a reusable module" heading:

````markdown
### Hero (house tool)

Every page's hero (the page-header band) renders from `partials/hero.twig`, pulled in by `{% block hero %}` in `base.twig`. **Never hand-roll a `<header>` in a `single-*` / `page-*` template** — override the block and reuse the partial. The partial exposes two named blocks so derivatives *extend* the shell instead of duplicating it:

- `heroMedia` — the optional full-bleed featured image
- `heroBody` — the title region (defaults to `<p>{{ title }}</p>`)

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

The hero title is a **label (`<p>`), not the page heading** — the theme does not use the page title as the `<h1>`. Each page's single `<h1>` is the derivative's responsibility, rendered in the content region; in practice the `<h1>` content writers add to the WYSIWYG body (`post.content`) covers it. `pnpm lint` fails any page template that hand-rolls a `<header>`.
````

- [ ] **Step 4: Add the "Things to avoid" bullet**

Find the last bullet of the "## Things to avoid" list:
```
- **No npm or yarn** — this project uses pnpm exclusively
```
Add a new bullet directly after it:
```
- **No hand-rolled page headers** — a `single-*`/`page-*` template must not contain its own `<header>`; override `{% block hero %}` and reuse `partials/hero.twig` (enforced by `pnpm lint`)
```

- [ ] **Step 5: Note the guardrail in "Build & dev workflow"**

In the workflow command block, find:
```
pnpm lint             # ESLint
```
Replace with:
```
pnpm lint             # ESLint + Twig hero-guardrail (no page template may hand-roll a <header>)
```

- [ ] **Step 6: Verify no stale references remain and the doc is coherent**

Run:
```bash
grep -rn "page-header\|pageHeader" AGENTS.md
grep -n "Hero (house tool)" AGENTS.md
```
Expected: the first grep returns nothing; the second finds the new heading.

- [ ] **Step 7: Commit**

```bash
git add AGENTS.md
git commit -m "docs: document the hero house tool, drop stale page-header refs

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```
Expected: commit succeeds.

---

## Task 4: Give content-less listing/utility pages an `<h1>` + harden the guardrail

**Origin:** the final whole-branch review found that demoting the hero title to `<p>` leaves `archive`, `search`, `404`, and the blog `index` headingless — their router-set `title` was previously the page's only `<h1>`, and they have no WYSIWYG body to supply one (a WCAG 1.3.1/2.4.6 regression). Two Minor guardrail-robustness notes are folded in.

**Files:**
- Modify: `views/archive.twig`, `views/search.twig`, `views/404.twig`, `views/index.twig`
- Modify: `scripts/lint-templates.mjs` (tolerant extends match)
- Modify: `scripts/lint-templates.test.mjs` (add a test for the tolerant match)
- Modify: `AGENTS.md` ("Hero (house tool)" section)

**Interfaces:**
- Consumes: `partials/hero.twig` with blocks `heroMedia`/`heroBody` (Task 1); `checkTemplate` (Task 2).
- Produces: the four listing/utility templates render `<h1>{{ title }}</h1>` via a `heroBody` override; `checkTemplate` now matches `extends` tolerantly.

- [ ] **Step 1: Promote the title to `<h1>` on the four listing/utility templates**

For **each** of `views/archive.twig`, `views/search.twig`, `views/404.twig`, `views/index.twig`, add a `{% block hero %}` override immediately after the `{% extends 'base.twig' %}` line (before the existing `{% block content %}`), leaving the rest of each file unchanged:

```twig
{% block hero %}
  {% embed 'partials/hero.twig' with { title } %}
    {% block heroBody %}<h1>{{ title }}</h1>{% endblock %}
  {% endembed %}
{% endblock %}
```

Rationale: on these pages the `title` *is* the heading. `content` pages (`page`/`single`/`front-page`) are NOT touched — they keep the default `<p>` label and get their `<h1>` from the WYSIWYG body.

- [ ] **Step 2: Confirm Prettier + the guardrail accept the edited templates**

Run:
```bash
pnpm format
pnpm lint
```
Expected: `pnpm format` exits 0 with no parser error; `pnpm lint` stays clean (the embed contains no literal `<header` — the shell lives in `hero.twig` — so no false positive).

- [ ] **Step 3: Write the failing test for tolerant `extends` matching**

Add this test to `scripts/lint-templates.test.mjs`:

```js
test('flags <header> even when extends uses double quotes and whitespace control', () => {
  const content = [
    '{%- extends "base.twig" -%}',
    '{% block hero %}',
    '  <header class="fluid-grid"><h1>{{ title }}</h1></header>',
    '{% endblock %}',
  ].join('\n');
  assert.deepEqual(checkTemplate('views/single-x.twig', content), {
    file: 'views/single-x.twig',
    line: 3,
  });
});
```

- [ ] **Step 4: Run it and watch it fail**

Run:
```bash
node --test 'scripts/*.test.mjs'
```
Expected: FAIL — the new test errors because the current exact-substring check (`content.includes("{% extends 'base.twig' %}")`) does not match the double-quoted, whitespace-controlled form, so `checkTemplate` returns `null` instead of the violation.

- [ ] **Step 5: Make the extends match tolerant**

In `scripts/lint-templates.mjs`, replace the exact-substring guard:

```js
  if (!content.includes("{% extends 'base.twig' %}")) return null;
```
with a tolerant regex (single/double quotes + optional whitespace-control):

```js
  // Tolerant of quote style and Twig whitespace-control, because the guard's
  // real audience is derivative sites where that authoring drift appears.
  if (!/\{%-?\s*extends\s+['"]base\.twig['"]/.test(content)) return null;
```

- [ ] **Step 6: Run the full spec suite green**

Run:
```bash
node --test 'scripts/*.test.mjs'
```
Expected: PASS — 5 tests, 0 failures (the four original + the new tolerant-match test).

- [ ] **Step 7: Update the AGENTS.md "Hero (house tool)" section**

In the "### Hero (house tool)" section, after the existing a11y paragraph, add:

```markdown
On content-less listing/utility pages (`archive`, `search`, `404`, the blog `index`) the router-set `title` *is* the page heading — those base templates override `heroBody` to render `<h1>{{ title }}</h1>` (a working reference for the pattern). Content pages keep the `<p>` label and get their `<h1>` from the WYSIWYG body. Note the guardrail also disallows a per-section `<article><header>` inside a page template — push that markup into a module (modules never extend `base.twig`); page-level meta belongs in `heroBody`.
```

- [ ] **Step 8: Full verification + commit**

Run:
```bash
pnpm lint && pnpm test
```
Expected: ESLint clean, hero-check clean, 5/5 tests pass.

```bash
git add views/archive.twig views/search.twig views/404.twig views/index.twig scripts/lint-templates.mjs scripts/lint-templates.test.mjs AGENTS.md
git commit -m "fix: give listing/utility pages an h1, harden extends match

archive/search/404/index override heroBody to render the title as <h1>
(they have no WYSIWYG body to supply one). The guardrail's extends check
now tolerates quote style and whitespace-control for derivative sites.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Final verification (whole plan)

- [ ] **Step 1: Full lint + test pass**

Run:
```bash
pnpm lint && pnpm test
```
Expected: ESLint clean, hero-check clean, `node --test` reports 4 passing tests.

- [ ] **Step 2: Format is stable**

Run:
```bash
pnpm format && git diff --stat
```
Expected: no unexpected reformatting of `views/partials/hero.twig`.

- [ ] **Step 3: Confirm the branch history**

Run:
```bash
git log --oneline main..HEAD
```
Expected: three commits — hero refactor, lint guardrail, docs.

- [ ] **Step 4: (Deferred, if no local WP earlier) Definition-of-Done eyeball**

Per AGENTS.md "Definition of done", with `WP_DEBUG` on, load the front page, a blog home with >1 page of posts, a category archive, a search with and without results, and a 404; confirm the hero renders the title as `<p>` and no template hand-rolls a header. Run one keyboard-only pass (skip link, nav, focus visible).

---

## Notes for the executor

- The `docs/superpowers/` spec and plan are process artifacts. Per AGENTS.md "Doc integrity", they are pruned from HEAD (tag first) when the work lands — do not treat them as product docs. AGENTS.md is the product doc and stays.
- Do not add a Twig test framework, a class hook on the hero `<p>`, speculative hero blocks, or a pre-commit hook — all explicitly out of scope (spec "Out of scope").
