import test from 'node:test';
import assert from 'node:assert/strict';
import { buildSync } from 'esbuild';

const out = buildSync({ entryPoints: ['src/zoom.ts'], bundle: true, format: 'esm', write: false }).outputFiles[0].text;
const { wheelFactor, doubleClickFactor, unprojectOrtho } = await import('data:text/javascript;base64,' + Buffer.from(out).toString('base64'));

test('a plain wheel notch is a fixed step, in when scrolling up', () => {
  assert.equal(wheelFactor(-100, false, 1.25), 1.25);
  assert.equal(wheelFactor(100, false, 1.25), 1 / 1.25);
  assert.equal(wheelFactor(-1, false, 1.2), 1.2, 'the size of the move does not matter without Ctrl');
});

test('Ctrl + wheel zooms smoothly by how far it moved, and never by more than about 1.5x per event', () => {
  assert.ok(wheelFactor(-3, true, 1.25) > 1 && wheelFactor(-3, true, 1.25) < 1.05, 'a small pinch zooms a little');
  assert.ok(wheelFactor(3, true, 1.25) < 1 && wheelFactor(3, true, 1.25) > 0.95);
  assert.ok(Math.abs(wheelFactor(-100, true, 1.25) - Math.exp(0.4)) < 1e-12, 'a mouse notch is capped');
  assert.ok(Math.abs(wheelFactor(100, true, 1.25) - Math.exp(-0.4)) < 1e-12);
  assert.equal(wheelFactor(0, true, 1.25), 1);
});

test('double-click zooms in, Shift + double-click zooms out by the same amount', () => {
  assert.equal(doubleClickFactor(false), 2);
  assert.equal(doubleClickFactor(true), 0.5);
  assert.equal(doubleClickFactor(false) * doubleClickFactor(true), 1);
});

// The globe's forward projection (see project() in src/globe/index.ts), y pointing down.
const RAD = Math.PI / 180;
const forward = (lat, lon, r, lat0, lon0) => {
  const la = lat * RAD, dl = lon * RAD - lon0;
  return { x: r * Math.cos(la) * Math.sin(dl), y: -r * (Math.cos(lat0) * Math.sin(la) - Math.sin(lat0) * Math.cos(la) * Math.cos(dl)), z: Math.sin(lat0) * Math.sin(la) + Math.cos(lat0) * Math.cos(la) * Math.cos(dl) };
};

test('the point under the pointer on the globe is found again from its screen position', () => {
  const r = 200, lat0 = 22 * RAD, lon0 = 10 * RAD;
  for (const [lat, lon] of [[47.5, 19], [0, 10], [-33.9, 151.2], [60, -100], [22, 10], [-60, 40], [5, -50], [80, 170]]) {
    const f = forward(lat, lon, r, lat0, lon0);
    if (f.z <= 0) { continue; } // far side: not under any pixel
    const back = unprojectOrtho(f.x, f.y, r, lat0, lon0);
    assert.ok(back, `${lat},${lon} found`);
    assert.ok(Math.abs(back.lat - lat) < 1e-6, `lat ${lat}`);
    const dl = ((back.lon - lon + 540) % 360) - 180;
    assert.ok(Math.abs(dl) < 1e-6, `lon ${lon}`);
  }
});

test('outside the globe there is no place, and the centre is the centre', () => {
  assert.equal(unprojectOrtho(300, 0, 200, 0, 0), null);
  assert.equal(unprojectOrtho(150, 150, 200, 0, 0), null);
  const c = unprojectOrtho(0, 0, 200, 22 * RAD, 10 * RAD);
  assert.ok(Math.abs(c.lat - 22) < 1e-9 && Math.abs(c.lon - 10) < 1e-9);
});
