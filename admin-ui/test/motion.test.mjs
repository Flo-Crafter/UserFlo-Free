import test from 'node:test';
import assert from 'node:assert/strict';
import { buildSync } from 'esbuild';

const out = buildSync({ entryPoints: ['src/motion.ts'], bundle: true, format: 'esm', write: false }).outputFiles[0].text;
const { animationsEnabled } = await import('data:text/javascript;base64,' + Buffer.from(out).toString('base64'));

test('animations run when the owner has them on (or never chose) and the device allows motion', () => {
  assert.equal(animationsEnabled(true, false), true);
  assert.equal(animationsEnabled(undefined, false), true, 'no setting handed over: on');
});

test('the owner can turn them off', () => {
  assert.equal(animationsEnabled(false, false), false);
});

test('the device\'s reduce-motion choice always wins', () => {
  assert.equal(animationsEnabled(true, true), false);
  assert.equal(animationsEnabled(undefined, true), false);
  assert.equal(animationsEnabled(false, true), false);
});
