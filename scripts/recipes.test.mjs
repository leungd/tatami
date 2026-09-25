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
