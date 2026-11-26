// Run with: npm test (Node's built-in test runner; esbuild, already a dev dependency, turns the TypeScript into a module).
import test from 'node:test';
import assert from 'node:assert/strict';
import { buildSync } from 'esbuild';

const out = buildSync({ entryPoints: ['src/columnOrder.ts'], bundle: true, format: 'esm', write: false }).outputFiles[0].text;
const { mergeOrder, moveItem, isDefaultOrder, flagCode } = await import('data:text/javascript;base64,' + Buffer.from(out).toString('base64'));

const D = ['visitor', 'type', 'first_seen', 'last_seen', 'sessions', 'pageviews', 'engaged', 'location', 'device'];

test('nothing saved gives the default order', () => {
  assert.deepEqual(mergeOrder(D, null), D);
  assert.deepEqual(mergeOrder(D, undefined), D);
  assert.deepEqual(mergeOrder(D, []), D);
  assert.notEqual(mergeOrder(D, []), D, 'a copy, never the default array itself');
});

test('a saved order is followed', () => {
  const saved = ['location', 'visitor', 'device', 'type', 'first_seen', 'last_seen', 'sessions', 'pageviews', 'engaged'];
  assert.deepEqual(mergeOrder(D, saved), saved);
});

test('columns that no longer exist are dropped and repeats ignored', () => {
  assert.deepEqual(mergeOrder(['a', 'b', 'c'], ['c', 'gone', 'a', 'c', 'b']), ['c', 'a', 'b']);
});

test('a column added by an update appears next to its default neighbour', () => {
  assert.deepEqual(mergeOrder(['a', 'b', 'new', 'c'], ['c', 'b', 'a']), ['c', 'b', 'new', 'a']);
  assert.deepEqual(mergeOrder(['new', 'a', 'b'], ['b', 'a']), ['b', 'a', 'new']);
});

test('moving a column', () => {
  assert.deepEqual(moveItem(['a', 'b', 'c', 'd'], 0, 2), ['b', 'c', 'a', 'd']);
  assert.deepEqual(moveItem(['a', 'b', 'c', 'd'], 3, 0), ['d', 'a', 'b', 'c']);
  assert.deepEqual(moveItem(['a', 'b'], 0, 99), ['b', 'a'], 'target is clamped');
  assert.deepEqual(moveItem(['a', 'b'], 5, 0), ['a', 'b'], 'a missing source changes nothing');
  const src = ['a', 'b', 'c']; moveItem(src, 0, 2); assert.deepEqual(src, ['a', 'b', 'c'], 'the input is not changed');
});

test('reset: an order equal to the default counts as default', () => {
  assert.equal(isDefaultOrder(D, D.slice()), true);
  assert.equal(isDefaultOrder(D, moveItem(D, 0, 1)), false);
  assert.equal(isDefaultOrder(D, D.slice(1)), false);
});

test('sorting and visibility are independent of order: the same ids come back, only in another order', () => {
  const o = mergeOrder(D, moveItem(D, 7, 0));
  assert.deepEqual([...o].sort(), [...D].sort());
  assert.equal(o[0], 'location');
});

test('flag codes: only two letters, any case; everything else gives no flag', () => {
  assert.equal(flagCode('hu'), 'HU');
  assert.equal(flagCode(' gb '), 'GB');
  for (const bad of ['', null, undefined, 'H', 'HUN', 'H1', '1U', 'ÁÉ', 42, {}, ['HU'], 'U S']) { assert.equal(flagCode(bad), '', String(bad)); }
});
