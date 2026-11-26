/** Pure helpers for the traffic calendar: date arithmetic on YYYY-MM-DD keys (no timezone involved) and the colour scale. */

export type Metric = 'pageviews' | 'sessions' | 'visitors' | 'events';
export const METRIC_KEYS: Metric[] = ['pageviews', 'sessions', 'visitors', 'events'];

const pad = (n: number) => String(n).padStart(2, '0');
const parse = (k: string): Date => { const [y, m, d] = k.split('-').map(Number); return new Date(Date.UTC(y, m - 1, d)); };
const fmt = (d: Date): string => `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`;

export const addDays = (k: string, n: number): string => { const d = parse(k); d.setUTCDate(d.getUTCDate() + n); return fmt(d); };
export const monthKey = (k: string): string => k.slice(0, 7);
export const monthStart = (k: string): string => `${k.slice(0, 7)}-01`;
export const monthEnd = (k: string): string => { const d = parse(monthStart(k)); d.setUTCMonth(d.getUTCMonth() + 1, 0); return fmt(d); };
export const addMonths = (k: string, n: number): string => {
  const d = parse(monthStart(k));
  d.setUTCMonth(d.getUTCMonth() + n, 1);
  return fmt(d);
};
/** First day of the week containing `k`, for a week that starts on `weekStart` (0 = Sunday … 6 = Saturday). */
export const weekStartOf = (k: string, weekStart: number): string => addDays(k, -((parse(k).getUTCDay() - weekStart + 7) % 7));
export const weekdayIndex = (k: string): number => parse(k).getUTCDay();
export const daysBetween = (a: string, b: string): number => Math.round((parse(b).getTime() - parse(a).getTime()) / 86400000);

export interface Cell { key: string; inMonth: boolean }

/** Month grid rows (weeks), padded with out-of-month cells so every row has seven. */
export function monthGrid(anchor: string, weekStart: number): Cell[][] {
  const first = monthStart(anchor);
  const last = monthEnd(anchor);
  const gridStart = weekStartOf(first, weekStart);
  const rows: Cell[][] = [];
  for (let k = gridStart; k <= last; k = addDays(k, 7)) {
    rows.push(Array.from({ length: 7 }, (_v, i) => { const key = addDays(k, i); return { key, inMonth: monthKey(key) === monthKey(first) }; }));
  }
  return rows;
}

export function weekRow(anchor: string, weekStart: number): Cell[] {
  const s = weekStartOf(anchor, weekStart);
  return Array.from({ length: 7 }, (_v, i) => ({ key: addDays(s, i), inMonth: true }));
}

export interface Scale {
  /** Level per input value: -1 = unavailable (null), 0 = zero, 1…4 = rank quarter among the non-zero values. */
  levels: number[];
  /** Per level 1…4 the smallest and largest value that received it (null when unused). */
  ranges: ({ min: number; max: number } | null)[];
  max: number;
  total: number;
}

/**
 * Colour levels by rank, not by distance to the maximum: a level is the quarter a value falls into among the non-zero
 * values shown, with ties sharing the higher level. One outlier therefore cannot flatten every other day, and the busiest
 * value is always level 4. Zero and unavailable values keep their own treatment.
 */
export function scale(values: (number | null)[]): Scale {
  const pos = values.filter((v): v is number => v !== null && v > 0).sort((a, b) => a - b);
  const n = pos.length;
  const ranges: Scale['ranges'] = [null, null, null, null];
  let total = 0;
  const levels = values.map((v) => {
    if (v === null) { return -1; }
    total += v;
    if (v <= 0) { return 0; }
    // Count of non-zero values <= v (upper bound).
    let lo = 0, hi = n;
    while (lo < hi) { const mid = (lo + hi) >> 1; if (pos[mid] <= v) { lo = mid + 1; } else { hi = mid; } }
    const lvl = Math.min(4, Math.max(1, Math.ceil((lo / n) * 4)));
    const r = ranges[lvl - 1];
    ranges[lvl - 1] = r ? { min: Math.min(r.min, v), max: Math.max(r.max, v) } : { min: v, max: v };
    return lvl;
  });
  return { levels, ranges, max: n ? pos[n - 1] : 0, total };
}
