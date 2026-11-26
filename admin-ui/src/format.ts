import { cfg as baseCfg } from './wp';

let localeOverride: string | null = null;
/** Formats dates and numbers in another locale (BCP 47) until reset with null; used when a report is drawn in its own language. */
export const setFormatLocale = (l: string | null): void => { localeOverride = l; };
const cfg = () => (localeOverride ? { ...baseCfg(), locale: localeOverride } : baseCfg());

function fmt(opts: Intl.DateTimeFormatOptions, iso: string): string {
  const d = new Date(iso);
  if (isNaN(d.getTime())) { return '—'; }
  const c = cfg();
  try {
    return new Intl.DateTimeFormat(c.locale, { ...opts, timeZone: c.timezone || 'UTC' }).format(d);
  } catch (e) {
    // Offset-only timezones: shift manually and format as UTC.
    return new Intl.DateTimeFormat(c.locale, { ...opts, timeZone: 'UTC' }).format(new Date(d.getTime() + c.utcOffset * 1000));
  }
}

export const dateTime = (iso: string | null): string => (iso ? fmt({ dateStyle: 'medium', timeStyle: 'short' }, iso) : '—');
export const dateOnly = (iso: string | null): string => (iso ? fmt({ dateStyle: 'medium' }, iso) : '—');
export const timeOnly = (iso: string): string => fmt({ timeStyle: 'medium' }, iso);

export function duration(sec: number): string {
  const s = Math.max(0, Math.round(sec));
  if (s < 60) { return `${s}s`; }
  if (s < 3600) { return `${Math.floor(s / 60)}m ${s % 60}s`; }
  return `${Math.floor(s / 3600)}h ${Math.floor((s % 3600) / 60)}m`;
}

export const num = (n: number): string => new Intl.NumberFormat(cfg().locale).format(n);

/** Today's date (YYYY-MM-DD) in the site timezone. */
export function todayKey(): string {
  return fmt({ year: 'numeric', month: '2-digit', day: '2-digit' }, new Date().toISOString()).replace(/(\d+)\D(\d+)\D(\d+)/, (_m, a, b, c) => (a.length === 4 ? `${a}-${b}-${c}` : `${c}-${a}-${b}`));
}

/** Short label for a site-local calendar day ("YYYY-MM-DD"): "14 Sep", or with the year when asked. */
export function dayLabel(ymd: string, withYear = false): string {
  const d = new Date(ymd + 'T00:00:00Z');
  if (isNaN(d.getTime())) { return ymd; }
  return new Intl.DateTimeFormat(cfg().locale, { day: 'numeric', month: 'short', ...(withYear ? { year: 'numeric' } : {}), timeZone: 'UTC' }).format(d);
}

/** A share (0..1) as a percentage with one decimal at most. */
export const pctText = (x: number): string => `${Math.round(x * 1000) / 10}%`;
