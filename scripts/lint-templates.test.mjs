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
