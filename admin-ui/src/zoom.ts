/**
 * The factor one mouse-wheel event zooms by. A plain wheel notch is a fixed step (in when scrolling up). With Ctrl held (and for a
 * touch-pad pinch, which browsers send as Ctrl + wheel) the zoom follows how far the wheel moved, so a small pinch zooms a little and a
 * mouse notch is capped at about one and a half times. Ctrl + wheel never zooms the whole browser page over a map: the maps stop that.
 */
export function wheelFactor(deltaY: number, ctrl: boolean, step: number): number {
  if (ctrl) { return Math.exp(Math.max(-0.4, Math.min(0.4, -deltaY * 0.01))); }
  return deltaY < 0 ? step : 1 / step;
}

/** Double-click zooms in on the pointer; with Shift it zooms out again. */
export const DOUBLE_CLICK_FACTOR = 2;
export const doubleClickFactor = (shift: boolean): number => (shift ? 1 / DOUBLE_CLICK_FACTOR : DOUBLE_CLICK_FACTOR);

/**
 * The place under a point of an orthographic globe (inverse of the globe's projection): centre (lat0, lon0) in radians, radius r pixels,
 * point (x, y) in pixels from the globe's centre (y down). Returns latitude and longitude in degrees, or null off the globe.
 */
export function unprojectOrtho(x: number, y: number, r: number, lat0: number, lon0: number): { lat: number; lon: number } | null {
  const px = x / r, py = -y / r, rho = Math.hypot(px, py);
  if (rho > 1) { return null; }
  if (rho < 1e-9) { return { lat: (lat0 * 180) / Math.PI, lon: (((lon0 * 180) / Math.PI + 540) % 360) - 180 }; }
  const c = Math.asin(rho), sc = Math.sin(c), cc = Math.cos(c);
  const lat = Math.asin(cc * Math.sin(lat0) + (py * sc * Math.cos(lat0)) / rho);
  const lon = lon0 + Math.atan2(px * sc, rho * Math.cos(lat0) * cc - py * Math.sin(lat0) * sc);
  return { lat: (lat * 180) / Math.PI, lon: ((((lon * 180) / Math.PI) % 360) + 540) % 360 - 180 };
}
