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
