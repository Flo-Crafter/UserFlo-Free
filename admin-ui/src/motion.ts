/**
 * Whether the dashboard may animate. Two things must both allow it: the owner's setting (Settings -> Branding and appearance, on by
 * default) and the visitor's own device setting "reduce motion", which always wins. Everything that moves is switched on through the
 * `uf-anim` class on the app's root element (see index.tsx), so with animations off the data simply appears.
 */
export function animationsEnabled(setting: boolean | undefined, reducedMotion: boolean): boolean {
  return setting !== false && !reducedMotion;
}

const reduced = (): boolean => typeof window !== 'undefined' && !!window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** The current answer for this page (reads the setting handed over by the server). */
export function animationsOn(): boolean {
  const app = typeof window !== 'undefined' ? window.userfloApp : undefined;
  return animationsEnabled(app ? app.animations : undefined, reduced());
}

/** True when a CSS custom property can be animated (used for the clockwise sweep of pie and ring charts); older browsers get a gentler turn instead. */
export function canSweep(): boolean {
  return typeof window !== 'undefined' && !!window.CSS && typeof (window.CSS as unknown as { registerProperty?: unknown }).registerProperty === 'function';
}

/** Everything that animates when it appears: charts, bars, donuts, sparklines and tables. */
export const ANIMATED = '.uf-reveal,.uf-kpi-spark,.uf-viz-svg,.uf-donut,.uf-viz-colbar,.uf-viz-stack,.uf-bl-bar,table.widefat';

/**
 * Starts each animation only when its chart or table is on screen. A new element is marked `uf-wait` (the CSS holds its animation at the
 * first frame) until it scrolls into view, so a chart below the fold still draws itself when you reach it. The position is read from the
 * element's layout box on scroll and resize (an IntersectionObserver would treat a chart whose first frame is clipped as not visible).
 * Without MutationObserver nothing is held back and everything animates on load as before.
 */
export function playWhenVisible(root: Element): () => void {
  if (typeof MutationObserver === 'undefined') { return () => undefined; }
  const waiting = new Set<Element>();
  let queued = false;
  const check = () => {
    queued = false;
    const vh = window.innerHeight || document.documentElement.clientHeight;
    waiting.forEach((el) => {
      const r = el.getBoundingClientRect();
      const h = Math.max(1, r.height);
      const visible = Math.min(r.bottom, vh) - Math.max(r.top, 0);
      if (visible >= Math.min(h, vh) * 0.12 || !el.isConnected) { el.classList.remove('uf-wait'); waiting.delete(el); }
    });
  };
  const soon = () => { if (!queued) { queued = true; window.requestAnimationFrame(check); } };
  const hold = (el: Element) => {
    if (el.classList.contains('uf-seen')) { return; }
    el.classList.add('uf-seen', 'uf-wait');
    waiting.add(el);
  };
  const scan = (n: Element) => { if (n.matches(ANIMATED)) { hold(n); } n.querySelectorAll(ANIMATED).forEach(hold); soon(); };
  const mo = new MutationObserver((list) => list.forEach((m) => m.addedNodes.forEach((n) => { if (n.nodeType === 1) { scan(n as Element); } })));
  mo.observe(root, { childList: true, subtree: true });
  window.addEventListener('scroll', soon, { passive: true, capture: true });
  window.addEventListener('resize', soon);
  scan(root);
  return () => { mo.disconnect(); window.removeEventListener('scroll', soon, true); window.removeEventListener('resize', soon); waiting.clear(); };
}
