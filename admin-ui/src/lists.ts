/** Pure helpers for rearrangeable cards and sortable tables (no UI, so they can be unit tested). */

export interface Sort { by: string; dir: 'asc' | 'desc' }

/** Saved order first (for the ids that still exist), then any id the saved layout does not know, in its default place. */
export function arrange(ids: string[], saved: string[]): string[] {
  const known = saved.filter((x, i) => ids.indexOf(x) > -1 && saved.indexOf(x) === i);
  return known.concat(ids.filter((x) => known.indexOf(x) < 0));
}

/** Moves one id to a position (drag and drop, or one step with the keyboard). */
export function moveId(order: string[], id: string, to: number): string[] {
  const from = order.indexOf(id);
  if (from < 0 || to < 0 || to >= order.length || to === from) { return order; }
  const out = order.slice(); out.splice(from, 1); out.splice(to, 0, id);
  return out;
}

/** The direction a column starts with when it is first chosen: text A to Z, numbers and dates highest/newest first. */
export const firstDir = (text: boolean): 'asc' | 'desc' => (text ? 'asc' : 'desc');

/** Sort rows in the browser: numbers by value, text by locale, empty values always last. */
export function sortRows<T>(rows: T[], sort: Sort, get: (r: T, by: string) => string | number | null | undefined): T[] {
  const f = sort.dir === 'asc' ? 1 : -1;
  return rows.map((r, i) => ({ r, i })).sort((a, b) => {
    const x = get(a.r, sort.by), y = get(b.r, sort.by);
    const ex = x === null || x === undefined || x === '', ey = y === null || y === undefined || y === '';
    if (ex || ey) { return ex && ey ? a.i - b.i : ex ? 1 : -1; }
    const c = typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), undefined, { numeric: true, sensitivity: 'base' });
    return c ? c * f : a.i - b.i;
  }).map((o) => o.r);
}
