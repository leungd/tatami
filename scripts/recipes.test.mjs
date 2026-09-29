import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const dir = new URL('../recipes/acf/', import.meta.url).pathname;
const files = readdirSync(dir).filter((f) => f.endsWith('.json'));

const keysOf = (node, out = []) => {
  if (Array.isArray(node)) {
    node.forEach((n) => keysOf(n, out));
  } else if (node && typeof node === 'object') {
    if (typeof node.key === 'string') out.push(node.key);
    Object.values(node).forEach((v) => keysOf(v, out));
  }
  return out;
};

test('recipes folder has ACF recipes', () => {
  assert.ok(files.length > 0);
});

for (const file of files) {
  test(`${file} parses and every key carries the SITE placeholder`, () => {
    const group = JSON.parse(readFileSync(join(dir, file), 'utf8'));
    assert.equal(group.key, file.replace(/\.json$/, ''));
    for (const key of keysOf(group)) {
      assert.match(
        key,
        /^(group|field)_SITE_/,
        `${key} is missing the SITE placeholder`,
      );
    }
  });
}

const themeDir = new URL('../', import.meta.url).pathname;
const themePrefix = 'wp-content/themes/tatami/';
const shipped = [
  'build/',
  'vendor/',
  'lib/',
  'views/',
  'acf-json/',
  '*.php',
  'style.css',
  'screenshot.png',
  'LICENSE',
];

// rsync semantics for top-level entries: a trailing / matches directories only, * never crosses /.
const matches = (rule, name, isDir) => {
  if (rule.endsWith('/') && !isDir) return false;
  const glob = rule.replace(/\/$/, '');
  const pattern = glob
    .replace(/[.+?^${}()|[\]\\]/g, '\\$&')
    .replace(/\*/g, '[^/]*');
  return new RegExp(`^${pattern}$`).test(name);
};

const wpeRules = readFileSync(join(themeDir, 'recipes/wpe/wpe-ignore'), 'utf8')
  .split('\n')
  .map((line) => line.trim())
  .filter((line) => line && !line.startsWith('#'))
  .map((line) =>
    line.startsWith(themePrefix) ? line.slice(themePrefix.length) : line,
  )
  .filter((rule) => !rule.replace(/\/$/, '').includes('/'));

const excluded = (name, isDir) =>
  wpeRules.some((rule) => matches(rule, name, isDir));

test('wpe-ignore keeps the runtime directories that may be absent from a checkout', () => {
  for (const dir of ['build', 'vendor', 'acf-json']) {
    assert.ok(!excluded(dir, true), `${dir}/ must ship`);
  }
});

test('every top-level theme entry either ships or is excluded by wpe-ignore, never both', () => {
  for (const entry of readdirSync(themeDir, { withFileTypes: true })) {
    const isDir = entry.isDirectory();
    const label = `${entry.name}${isDir ? '/' : ''}`;
    const ships = shipped.some((rule) => matches(rule, entry.name, isDir));
    const skipped = excluded(entry.name, isDir);
    assert.ok(
      ships || skipped,
      `${label} is neither shipped nor in recipes/wpe/wpe-ignore`,
    );
    assert.ok(
      !(ships && skipped),
      `${label} must ship but recipes/wpe/wpe-ignore excludes it`,
    );
  }
});
