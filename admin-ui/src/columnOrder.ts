/** Column order logic, kept free of the DOM so it can be tested on its own. */

/**
 * The order to show the columns in: the saved order first (ids that no longer exist are skipped, repeats are ignored), then any column the
 * saved order does not mention - for example one added by an update - in its default place relative to the others, after the last saved
 * neighbour. With nothing saved this is the default order.
 */
export function mergeOrder(defaults: string[], saved: string[] | null | undefined): string[] {
  if (!saved || !saved.length) { return defaults.slice(); }
  const known = new Set(defaults);
  const out: string[] = [];
  saved.forEach((id) => { if (known.has(id) && out.indexOf(id) < 0) { out.push(id); } });
  const missing = defaults.filter((id) => out.indexOf(id) < 0);
  missing.forEach((id) => {
    // Put a new column right after the column that precedes it in the default order, when that one is already placed.
    const prev = defaults[defaults.indexOf(id) - 1];
    const at = prev !== undefined ? out.indexOf(prev) : -1;
    if (at >= 0) { out.splice(at + 1, 0, id); } else { out.push(id); }
  });
  return out;
}

/** Moves the item at `from` to position `to` (both clamped). Returns a new array. */
export function moveItem<T>(list: T[], from: number, to: number): T[] {
  if (from < 0 || from >= list.length) { return list.slice(); }
  const t = Math.max(0, Math.min(list.length - 1, to));
  const out = list.slice();
  const [x] = out.splice(from, 1);
  out.splice(t, 0, x);
  return out;
}

/** True when the saved order is just the default order (nothing worth keeping). */
export function isDefaultOrder(defaults: string[], order: string[]): boolean {
  return defaults.length === order.length && defaults.every((id, i) => order[i] === id);
}

/** A flag is only drawn for a two-letter code; anything else (empty, a digit, three letters) gets none. Returns the upper-case code or ''. */
export function flagCode(code: unknown): string {
  const c = typeof code === 'string' ? code.trim().toUpperCase() : '';
  return /^[A-Z]{2}$/.test(c) ? c : '';
}
