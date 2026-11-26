import { h, __, cfg, useState, useEffect, useRef, useMemo } from '../wp';
import { get } from '../api';
import type { ApiError } from '../api';
import { num } from '../format';
import { addDays, addMonths, monthGrid, weekRow, weekStartOf, METRIC_KEYS } from '../traffic';
import type { Cell, Metric, Scale } from '../traffic';
import type { TrafficCalendarData, TrafficDay } from '../types';

/** Pieces of the traffic calendar used by both the Lite month view and the Pro calendar. */
export type View = 'month' | 'week';
export type Phase = 'closed' | 'opening' | 'open' | 'closing';

/** Today's date in the site timezone (the server's value wins once the calendar has loaded). */
export function siteToday(): string {
  try {
    return new Intl.DateTimeFormat('en-CA', { timeZone: cfg().timezone || 'UTC', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  } catch (e) {
    return new Date(Date.now() + (cfg().utcOffset || 0) * 1000).toISOString().slice(0, 10);
  }
}

export const dateOf = (k: string): Date => { const [y, m, d] = k.split('-').map(Number); return new Date(Date.UTC(y, m - 1, d)); };
export const fmtDate = (k: string, o: Intl.DateTimeFormatOptions): string => new Intl.DateTimeFormat(cfg().locale, { ...o, timeZone: 'UTC' }).format(dateOf(k));
export const compact = (n: number): string => new Intl.NumberFormat(cfg().locale, { notation: 'compact', maximumFractionDigits: 1 }).format(n);

export const METRIC_INFO: Record<Metric, { label: () => string; hint: () => string; unit: (n: number) => string }> = {
  pageviews: { label: () => __('Page views'), hint: () => __('Every page a visitor loads. One visit can include many page views.'), unit: (n) => (n === 1 ? __('page view') : __('page views')) },
  sessions: { label: () => __('Sessions'), hint: () => __('Visits, counted on the day and hour they started. A visit is one browsing session.'), unit: (n) => (n === 1 ? __('session') : __('sessions')) },
  visitors: { label: () => __('Unique visitors'), hint: () => __('Different visitors whose session started in that period. A returning visitor counts once per day, so hourly values do not add up to the day.'), unit: (n) => (n === 1 ? __('unique visitor') : __('unique visitors')) },
  events: { label: () => __('Events'), hint: () => __('Recorded interactions: clicks, form submissions, downloads and custom events.'), unit: (n) => (n === 1 ? __('event') : __('events')) },
};

export const val = (d: { pageviews?: number | null; sessions?: number | null; visitors?: number | null; events?: number | null } | undefined, m: Metric): number | null => (d && d[m] !== undefined ? (d[m] as number | null) : null);

export function MetricPicker(p: { metric: Metric; onChange: (m: Metric) => void }) {
  return (
    <div className="uf-tc-metrics">
      <div className="uf-subtabs" role="group" aria-label={__('Metric')}>
        {METRIC_KEYS.map((m) => (
          <button key={m} type="button" className={'button' + (p.metric === m ? ' button-primary' : '')} aria-pressed={p.metric === m} onClick={() => p.onChange(m)}>{METRIC_INFO[m].label()}</button>
        ))}
      </div>
      <p className="uf-tc-hint" id="uf-tc-metric-hint">{METRIC_INFO[p.metric].hint()}</p>
    </div>
  );
}

export function Legend(p: { sc: Scale; metric: Metric; partial: boolean }) {
  const used = p.sc.ranges.map((r, i) => ({ r, i })).filter((x) => x.r);
  const range = (r: { min: number; max: number }) => (r.min === r.max ? num(r.min) : `${num(r.min)}–${num(r.max)}`);
  return (
    <div className="uf-tc-legend" aria-label={__('Legend')} role="group">
      <span className="uf-tc-leg-end">{__('Less activity')}</span>
      <ul>
        <li><span className="uf-tc-sw" data-level="0" aria-hidden="true" /><small>0</small></li>
        {[1, 2, 3, 4].map((l) => {
          const r = p.sc.ranges[l - 1];
          return <li key={l} className={r ? '' : 'is-unused'}><span className="uf-tc-sw" data-level={l} aria-hidden="true" /><small>{r ? range(r) : '–'}</small></li>;
        })}
      </ul>
      <span className="uf-tc-leg-end">{__('More activity')}</span>
      <ul className="uf-tc-leg-extra">
        <li><span className="uf-tc-sw" data-level="-1" aria-hidden="true" /><small>{__('No data recorded')}</small></li>
        {p.partial ? <li><span className="uf-tc-sw" data-level="0" data-partial="1" aria-hidden="true" /><small>{__('Partial day')}</small></li> : null}
      </ul>
      <p className="uf-tc-leg-note">{used.length ? __('Colours show how each day ranks among the days shown, so one very busy day does not hide the others. Darkest = the busiest quarter.') : ''}</p>
    </div>
  );
}

export function tileLabel(key: string, d: TrafficDay | HourLike | undefined, metric: Metric, extra: string[]): string {
  const when = fmtDate(key, { dateStyle: 'full' });
  const bits = [when];
  const v = val(d, metric);
  if (!d) { bits.push(__('loading')); } else if (d.state === 'future') { bits.push(__('in the future, no data yet')); } else if (d.state === 'nodata' || v === null) { bits.push(__('no data recorded')); } else { bits.push(`${num(v)} ${METRIC_INFO[metric].unit(v)}`); }
  return bits.concat(extra).join(', ');
}
export type HourLike = { state: string; pageviews?: number | null; sessions?: number | null; visitors?: number | null; events?: number | null };

export function tip(key: string, d: TrafficDay | undefined, metric: Metric): string {
  const v = val(d, metric);
  const date = fmtDate(key, { weekday: 'short', day: 'numeric', month: 'short' });
  if (!d) { return date; }
  if (d.state === 'future') { return `${date} · ${__('no data yet')}`; }
  if (d.state === 'nodata' || v === null) { return `${date} · ${__('no data recorded')}`; }
  return `${date} · ${num(v)} ${METRIC_INFO[metric].unit(v)}${d.partial ? ' · ' + __('partial day') : ''}`;
}

export interface GridProps {
  view: View; anchor: string; weekStart: number; today: string; metric: Metric;
  days: Record<string, TrafficDay>; sc: Scale; keys: string[];
  focusKey: string; selected: string | null; busy: boolean; interactive: boolean;
  onFocusKey: (k: string) => void; onOpen: (k: string, el: HTMLElement) => void; onMove: (k: string) => void;
}

export function Grid(p: GridProps) {
  const c = cfg();
  const wd = useMemo(() => Array.from({ length: 7 }, (_v, i) => {
    const d = new Date(Date.UTC(2023, 0, 1 + ((c.weekStart + i) % 7)));
    return { short: new Intl.DateTimeFormat(c.locale, { weekday: 'short', timeZone: 'UTC' }).format(d), long: new Intl.DateTimeFormat(c.locale, { weekday: 'long', timeZone: 'UTC' }).format(d) };
  }), [c.weekStart, c.locale]);
  const rows: Cell[][] = p.view === 'month' ? monthGrid(p.anchor, p.weekStart) : [weekRow(p.anchor, p.weekStart)];
  const level = useMemo(() => { const m: Record<string, number> = {}; p.keys.forEach((k, i) => { m[k] = p.sc.levels[i]; }); return m; }, [p.keys, p.sc]);

  const onKey = (e: KeyboardEvent, key: string) => {
    let t: string | null = null;
    if (e.key === 'ArrowLeft') { t = addDays(key, -1); } else if (e.key === 'ArrowRight') { t = addDays(key, 1); } else if (e.key === 'ArrowUp') { t = addDays(key, -7); } else if (e.key === 'ArrowDown') { t = addDays(key, 7); }
    else if (e.key === 'Home') { t = weekStartOf(key, p.weekStart); } else if (e.key === 'End') { t = addDays(weekStartOf(key, p.weekStart), 6); }
    else if (e.key === 'PageUp') { t = p.view === 'month' ? addMonths(key, -1) : addDays(key, -7); } else if (e.key === 'PageDown') { t = p.view === 'month' ? addMonths(key, 1) : addDays(key, 7); }
    if (t) { e.preventDefault(); p.onMove(t); }
  };

  return (
    <table className={'uf-tc-grid uf-tc-' + p.view} role="grid" aria-label={p.view === 'month' ? fmtDate(p.anchor, { month: 'long', year: 'numeric' }) : __('Week')} aria-describedby="uf-tc-metric-hint" aria-busy={p.busy}>
      <thead><tr role="row">{wd.map((w) => <th key={w.long} scope="col" role="columnheader"><abbr title={w.long}>{w.short}</abbr></th>)}</tr></thead>
      <tbody>
        {rows.map((r, ri) => (
          <tr key={ri} role="row">
            {r.map((cell, ci) => {
              if (!cell.inMonth) { return <td key={ci} role="gridcell" className="uf-tc-off" aria-hidden="true" />; }
              const d = p.days[cell.key];
              const lvl = d ? (level[cell.key] === undefined ? -1 : level[cell.key]) : -2;
              const v = val(d, p.metric);
              const sel = p.selected === cell.key;
              const extra = [cell.key === p.today ? __('today') : '', d && d.partial && d.state === 'data' ? __('partial day') : '', sel ? __('selected') : ''].filter(Boolean);
              return (
                <td key={ci} role="gridcell">
                  {!p.interactive ? (
                    // Lite: a read-only month overview (no day or hour detail), so tiles are labelled values, not buttons.
                    <div className="uf-tc-tile is-static" role="img" data-level={lvl} data-partial={d && d.partial && d.state === 'data' ? '1' : undefined} data-today={cell.key === p.today ? '1' : undefined}
                      aria-label={tileLabel(cell.key, d, p.metric, extra)}>
                      <span className="uf-tc-n" aria-hidden="true">{Number(cell.key.slice(8))}</span>
                      <span className="uf-tc-v" aria-hidden="true" data-v={v === null ? (d && d.state === 'future' ? '' : '–') : compact(v)} />
                    </div>
                  ) : (
                  <button type="button" className="uf-tc-tile" data-date={cell.key} data-level={lvl} data-partial={d && d.partial && d.state === 'data' ? '1' : undefined}
                    data-today={cell.key === p.today ? '1' : undefined} data-col={ci} data-tip={tip(cell.key, d, p.metric)}
                    tabIndex={cell.key === p.focusKey ? 0 : -1} aria-pressed={sel} aria-label={tileLabel(cell.key, d, p.metric, extra)}
                    onFocus={() => p.onFocusKey(cell.key)} onKeyDown={(e) => onKey(e as unknown as KeyboardEvent, cell.key)}
                    onClick={(e) => p.onOpen(cell.key, e.currentTarget as HTMLElement)}>
                    <span className="uf-tc-n" aria-hidden="true">{Number(cell.key.slice(8))}</span>
                    <span className="uf-tc-v" aria-hidden="true" data-v={v === null ? (d && d.state === 'future' ? '' : '–') : compact(v)} />
                  </button>
                  )}
                </td>
              );
            })}
          </tr>
        ))}
      </tbody>
    </table>
  );
}


export interface CalState { data: TrafficCalendarData | null; error: ApiError | null; loading: boolean; reload: () => void }

/**
 * Loads the selected metric for the visible period (one aggregate query; the others are fetched only when chosen).
 * Finished periods are remembered for the session so switching back and forth is instant; any period that includes
 * today is always fetched again, and `data` is only ever the response that matches the current request.
 */
export function useCalendar(from: string, to: string, metric: Metric): CalState {
  const cache = useRef<Record<string, TrafficCalendarData>>({});
  const key = `${from}|${to}|${metric}`;
  const [res, setRes] = useState<{ key: string; data: TrafficCalendarData | null; error: ApiError | null }>({ key: '', data: null, error: null });
  const [tick, setTick] = useState(0);
  useEffect(() => {
    const hit = cache.current[key];
    if (hit) { setRes({ key, data: hit, error: null }); return undefined; }
    const ctl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    let live = true;
    get<TrafficCalendarData>('stats/traffic/calendar', { from, to, metric }, ctl ? ctl.signal : undefined)
      .then((d) => { if (!live) { return; } if (to < d.today) { cache.current[key] = d; } setRes({ key, data: d, error: null }); })
      .catch((e: ApiError & { name?: string }) => { if (live && e.name !== 'AbortError') { setRes({ key, data: null, error: e }); } });
    return () => { live = false; if (ctl) { ctl.abort(); } };
  }, [key, tick]);
  const fresh = res.key === key;
  return { data: fresh ? res.data : null, error: fresh ? res.error : null, loading: !fresh || (res.data === null && res.error === null), reload: () => { delete cache.current[key]; setRes({ key: '', data: null, error: null }); setTick((t) => t + 1); } };
}

