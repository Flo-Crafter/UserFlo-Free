/** Pure helpers for the Overview charts (no DOM, no WordPress): scales, smooth paths, bucketing, shares. Unit-tested in dev/unit-chart.mjs. */

export interface Pt { x: number; y: number }

/** A "nice" axis maximum and tick step for a data maximum (1, 2, 2.5 or 5 times a power of ten). */
export function niceScale(max: number, ticks = 4): { max: number; step: number } {
  if (!(max > 0)) { return { max: 1, step: 1 }; }
  const raw = max / ticks;
  const pow = Math.pow(10, Math.floor(Math.log10(raw)));
  const f = raw / pow;
  const step = (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * pow;
  return { max: Math.ceil(max / step) * step, step };
}

/**
 * Smooth line through the points using monotone cubic interpolation (Fritsch–Carlson): the curve never overshoots the data, so it cannot
 * invent a peak or dip below zero between two recorded values.
 */
export function smoothPath(pts: Pt[]): string {
  const n = pts.length;
  if (n === 0) { return ''; }
  const f = (v: number) => (Math.round(v * 100) / 100).toString();
  if (n === 1) { return `M${f(pts[0].x)},${f(pts[0].y)}`; }
  if (n === 2) { return `M${f(pts[0].x)},${f(pts[0].y)}L${f(pts[1].x)},${f(pts[1].y)}`; }
  const dx: number[] = [], m: number[] = [], t: number[] = new Array(n);
  for (let i = 0; i < n - 1; i++) { dx[i] = pts[i + 1].x - pts[i].x; m[i] = dx[i] === 0 ? 0 : (pts[i + 1].y - pts[i].y) / dx[i]; }
  t[0] = m[0]; t[n - 1] = m[n - 2];
  for (let i = 1; i < n - 1; i++) { t[i] = m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2; }
  for (let i = 0; i < n - 1; i++) {
    if (m[i] === 0) { t[i] = 0; t[i + 1] = 0; continue; }
    const a = t[i] / m[i], b = t[i + 1] / m[i], s = a * a + b * b;
    if (s > 9) { const k = 3 / Math.sqrt(s); t[i] = k * a * m[i]; t[i + 1] = k * b * m[i]; }
  }
  let d = `M${f(pts[0].x)},${f(pts[0].y)}`;
  for (let i = 0; i < n - 1; i++) {
    const h = dx[i] / 3;
    d += `C${f(pts[i].x + h)},${f(pts[i].y + t[i] * h)},${f(pts[i + 1].x - h)},${f(pts[i + 1].y - t[i + 1] * h)},${f(pts[i + 1].x)},${f(pts[i + 1].y)}`;
  }
  return d;
}

export interface DayRow { date: string; sessions: number; pageviews: number; visitors: number; engaged: number; single: number }
export interface Bucket extends DayRow { /** Last day of the bucket (equals `date` for a single day). */ end: string; days: number }

/** Days per bucket so a range never draws more than `maxPoints` points: 1 up to ~3 months, then whole weeks. */
export function bucketSize(days: number, maxPoints = 92): number {
  return days <= maxPoints ? 1 : 7 * Math.ceil(days / 7 / maxPoints);
}

/**
 * Groups consecutive days. Counts are summed; the visitor figure of a bucket is the sum of daily distinct visitors (a visitor seen on two
 * days counts twice), which is why the chart labels it "daily visitors" and never compares it with the KPI total.
 */
export function bucketDays(rows: DayRow[], size: number): Bucket[] {
  if (size <= 1) { return rows.map((r) => ({ ...r, end: r.date, days: 1 })); }
  const out: Bucket[] = [];
  for (let i = 0; i < rows.length; i += size) {
    const part = rows.slice(i, i + size);
    out.push({
      date: part[0].date, end: part[part.length - 1].date, days: part.length,
      sessions: sum(part, 'sessions'), pageviews: sum(part, 'pageviews'), visitors: sum(part, 'visitors'), engaged: sum(part, 'engaged'), single: sum(part, 'single'),
    });
  }
  return out;
}

const sum = (rows: DayRow[], k: keyof DayRow): number => rows.reduce((a, r) => a + (r[k] as number), 0);

/** Share of a total as a whole-number percentage string ("<1%" for non-zero values that round to zero, "—" without a total). */
export function share(value: number, total: number): string {
  if (!(total > 0)) { return '—'; }
  const p = (100 * value) / total;
  return p > 0 && p < 1 ? '<1%' : Math.round(p) + '%';
}

/** Compact number: 1.2k, 3.4M (for axis labels where space is tight). */
export function compact(n: number): string {
  const a = Math.abs(n);
  if (a >= 1e6) { return trim(n / 1e6) + 'M'; }
  if (a >= 1e4) { return Math.round(n / 1e3) + 'k'; }
  if (a >= 1e3) { return trim(n / 1e3) + 'k'; }
  return String(Math.round(n * 10) / 10);
}
const trim = (v: number): string => (Math.round(v * 10) / 10).toString();

/** Index of the point nearest to x for evenly spaced points between x0 and x1 (n >= 1). */
export function nearestIndex(x: number, x0: number, x1: number, n: number): number {
  if (n <= 1 || x1 <= x0) { return 0; }
  return Math.max(0, Math.min(n - 1, Math.round(((x - x0) / (x1 - x0)) * (n - 1))));
}

/** Arc segments of a donut: start and end angle (radians, 0 at 12 o'clock, clockwise) for each value; zero values get no arc. */
export function donutArcs(values: number[]): { a0: number; a1: number }[] {
  const total = values.reduce((a, b) => a + Math.max(0, b), 0);
  let a = 0;
  return values.map((v) => {
    const span = total > 0 ? (Math.max(0, v) / total) * Math.PI * 2 : 0;
    const r = { a0: a, a1: a + span };
    a += span;
    return r;
  });
}

/** SVG path for a donut segment between two angles (outer radius r1, inner r0), centred on (cx, cy). */
export function arcPath(cx: number, cy: number, r0: number, r1: number, a0: number, a1: number): string {
  const full = a1 - a0 >= Math.PI * 2 - 1e-6;
  const e = full ? a0 + Math.PI * 2 - 1e-4 : a1;
  const p = (r: number, a: number) => `${(cx + r * Math.sin(a)).toFixed(2)},${(cy - r * Math.cos(a)).toFixed(2)}`;
  const big = e - a0 > Math.PI ? 1 : 0;
  return `M${p(r1, a0)}A${r1},${r1},0,${big},1,${p(r1, e)}L${p(r0, e)}A${r0},${r0},0,${big},0,${p(r0, a0)}Z`;
}

/** Corners of a radar (star) chart: one spoke per value, 0 at 12 o'clock, radius proportional to value / max (a floor keeps tiny values visible). */
export function radarPoints(values: number[], cx: number, cy: number, r: number, max?: number): Pt[] {
  const m = max && max > 0 ? max : Math.max(1, ...values);
  const n = values.length;
  return values.map((v, i) => {
    const a = (Math.PI * 2 * i) / n;
    const rr = r * Math.max(0.04, Math.min(1, Math.max(0, v) / m));
    return { x: cx + rr * Math.sin(a), y: cy - rr * Math.cos(a) };
  });
}

/** Tip of spoke i of n at full radius (for the axis lines and labels). */
export function spoke(i: number, n: number, cx: number, cy: number, r: number): Pt {
  const a = (Math.PI * 2 * i) / n;
  return { x: cx + r * Math.sin(a), y: cy - r * Math.cos(a) };
}

/** Rose (polar area) wedges: equal angles, radius proportional to the square root of the value so the AREA follows the value. */
export function roseWedges(values: number[], r0: number, r1: number): { a0: number; a1: number; r: number }[] {
  const n = values.length || 1;
  const m = Math.max(1, ...values);
  return values.map((v, i) => ({ a0: (Math.PI * 2 * i) / n, a1: (Math.PI * 2 * (i + 1)) / n, r: r0 + (r1 - r0) * Math.sqrt(Math.max(0, v) / m) }));
}
