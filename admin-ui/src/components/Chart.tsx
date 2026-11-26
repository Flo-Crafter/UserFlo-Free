import { h, __, useState, useEffect, useRef, useMemo, Fragment } from '../wp';
import type * as React from 'react';
import { dayLabel, num } from '../format';
import { arcPath, bucketDays, bucketSize, compact, donutArcs, nearestIndex, niceScale, share, smoothPath } from '../chartMath';
import type { Bucket, DayRow } from '../chartMath';
import { CycleTitle } from './ui';

export type TrendStyle = 'flow' | 'sharp' | 'steps' | 'dots' | 'bars' | 'candles';
const TREND_STYLES: TrendStyle[] = ['flow', 'sharp', 'steps', 'dots', 'bars', 'candles'];
const trendStyleLabel = (t: TrendStyle): string => ({ flow: __('Flowing lines'), sharp: __('Sharp lines'), steps: __('Steps'), dots: __('Dots'), bars: __('Bars'), candles: __('Candles') }[t]);
const TREND_KEY = 'userflo_cycle_v1_trend';
const storedTrend = (): TrendStyle => {
  try { const v = window.localStorage.getItem(TREND_KEY) as TrendStyle | null; if (v && TREND_STYLES.indexOf(v) > -1) { return v; } } catch (e) { /* storage unavailable */ }
  return 'flow';
};

/** Series colour as a CSS custom property so every UI style can recolour it (see --uf-series-N in admin.css and themes.css). */
const colour = (k: number): React.CSSProperties => ({ ['--k' as string]: k === 0 ? 'var(--uf-axis)' : `var(--uf-series-${k})` } as React.CSSProperties);

/** Width of an element in pixels, kept current with a ResizeObserver (charts are drawn at their real size so text is never stretched). */
function useWidth(): [React.RefObject<HTMLDivElement>, number] {
  const ref = useRef<HTMLDivElement>(null);
  const [w, setW] = useState(0);
  useEffect(() => {
    const el = ref.current;
    if (!el) { return; }
    setW(el.clientWidth);
    if (typeof ResizeObserver === 'undefined') { return; }
    const ro = new ResizeObserver(() => setW(el.clientWidth));
    ro.observe(el);
    return () => ro.disconnect();
  }, []);
  return [ref, w];
}

/** Tiny trend line for a KPI card. Decorative for the eye; the card's text carries the figures, and the label summarises the line. */
export function Sparkline(p: { values: number[]; k: number; label: string }) {
  const n = p.values.length;
  if (n < 3 || p.values.every((v) => v === 0)) { return null; }
  const W = 88, H = 30, pad = 2;
  const max = Math.max(...p.values, 1);
  const pts = p.values.map((v, i) => ({ x: (i * W) / (n - 1), y: pad + (1 - v / max) * (H - 2 * pad) }));
  const line = smoothPath(pts);
  const area = `${line}L${W},${H}L0,${H}Z`;
  return (
    <svg className="uf-kpi-spark" viewBox={`0 0 ${W} ${H}`} role="img" aria-label={p.label} style={colour(p.k)} preserveAspectRatio="none">
      <path className="uf-spark-area" d={area} />
      <path className="uf-spark-line" d={line} />
    </svg>
  );
}

export interface TrendSeries { key: 'pageviews' | 'sessions' | 'visitors'; label: string; k: number }

/**
 * Traffic over time: smooth lines for the chosen metrics, an optional dashed line for the comparison period, axis labels, a crosshair
 * tooltip that follows the pointer or the arrow keys, and the same figures as a table. Ranges longer than about three months are grouped by
 * week so the chart never draws hundreds of points.
 */
export function TrendChart(p: { rows: DayRow[]; prev: DayRow[] | null; compareLabel: string; title: string }) {
  const series: TrendSeries[] = [
    { key: 'pageviews', label: __('Page views'), k: 1 },
    { key: 'sessions', label: __('Sessions'), k: 2 },
    { key: 'visitors', label: __('Visitors (per day)'), k: 3 },
  ];
  const [on, setOn] = useState<Record<string, boolean>>({ pageviews: true, sessions: true, visitors: false });
  const [idx, setIdx] = useState<number | null>(null);
  const [style, setStyle] = useState<TrendStyle>(storedTrend);
  const cycle = () => {
    const nx = TREND_STYLES[(TREND_STYLES.indexOf(style) + 1) % TREND_STYLES.length];
    setStyle(nx);
    try { window.localStorage.setItem(TREND_KEY, nx); } catch (e) { /* ignore */ }
  };
  const [wrap, width] = useWidth();
  const size = bucketSize(p.rows.length);
  const cur = useMemo(() => bucketDays(p.rows, size), [p.rows, size]);
  const prev = useMemo(() => (p.prev ? bucketDays(p.prev, size) : null), [p.prev, size]);
  const shown = series.filter((s) => on[s.key]);
  const n = cur.length;

  if (!n || p.rows.every((d) => d.sessions === 0 && d.pageviews === 0)) {
    return <p className="uf-empty">{__('No activity recorded in this period.')}</p>;
  }

  const W = Math.max(280, width || 700), H = 260;
  const m = { l: 44, r: 14, t: 12, b: 28 };
  const pw = W - m.l - m.r, ph = H - m.t - m.b;
  const vals = (s: TrendSeries, rows: Bucket[] | null): number[] => (rows ? rows.map((r) => r[s.key]) : []);
  let max = 0;
  shown.forEach((s) => { max = Math.max(max, ...vals(s, cur), ...vals(s, prev)); });
  const sc = niceScale(max, W < 420 ? 3 : 4);
  const X = (i: number) => m.l + (n === 1 ? pw / 2 : (i * pw) / (n - 1));
  const Y = (v: number) => m.t + ph - (v / sc.max) * ph;
  const label = (b: Bucket) => (b.days > 1 ? `${dayLabel(b.date)} – ${dayLabel(b.end, true)}` : dayLabel(b.date, true));
  const yTicks: number[] = [];
  for (let v = 0; v <= sc.max + 1e-9; v += sc.step) { yTicks.push(v); }
  const want = Math.max(2, Math.min(n, Math.floor(pw / 86)));
  const xTicks = Array.from(new Set(Array.from({ length: want }, (_, i) => Math.round((i * (n - 1)) / Math.max(1, want - 1)))));

  const move = (e: React.PointerEvent<HTMLDivElement>) => {
    const r = e.currentTarget.getBoundingClientRect();
    setIdx(nearestIndex(e.clientX - r.left, m.l, m.l + pw, n));
  };
  const key = (e: React.KeyboardEvent<HTMLDivElement>) => {
    const at = idx === null ? n - 1 : idx;
    const next = e.key === 'ArrowLeft' ? at - 1 : e.key === 'ArrowRight' ? at + 1 : e.key === 'Home' ? 0 : e.key === 'End' ? n - 1 : null;
    if (next === null) { return; }
    e.preventDefault();
    setIdx(Math.max(0, Math.min(n - 1, next)));
  };

  const at = idx !== null && idx < n ? idx : null;
  const delta = (a: number, b: number | undefined): string => {
    if (b === undefined) { return ''; }
    if (b === 0) { return a === 0 ? '' : ` · ${__('new')}`; }
    const d = Math.round(((a - b) / b) * 100);
    return ` · ${d > 0 ? '+' : ''}${d}%`;
  };
  const sr = at === null ? '' : `${label(cur[at])}: ` + shown.map((s) => `${s.label} ${num(cur[at][s.key])}` + (prev && prev[at] ? ` (${p.compareLabel} ${num(prev[at][s.key])})` : '')).join(', ');
  const first = shown[0];

  return (
    <div className="uf-trend">
      <div className="uf-trend-head">
        <CycleTitle title={p.title} view={trendStyleLabel(style)} onCycle={cycle} />
        <div className="uf-trend-legend" role="group" aria-label={__('Metrics shown in the chart')}>
          {series.map((s) => (
            <button key={s.key} type="button" className="uf-trend-toggle" style={colour(s.k)} aria-pressed={!!on[s.key]}
              onClick={() => setOn({ ...on, [s.key]: !on[s.key] })}><i />{s.label}</button>
          ))}
        </div>
      </div>
      <div ref={wrap} className="uf-trend-plot" tabIndex={0} role="group" aria-label={__('Traffic chart. Use the left and right arrow keys to move between points.')}
        onPointerMove={move} onPointerDown={move} onPointerLeave={() => setIdx(null)} onKeyDown={key} onFocus={() => setIdx((i) => (i === null ? n - 1 : i))} onBlur={() => setIdx(null)}>
        <svg viewBox={`0 0 ${W} ${H}`} height={H} aria-hidden="true" focusable="false">
          {yTicks.map((v) => (
            <g key={v}>
              <line className="uf-trend-grid" x1={m.l} x2={W - m.r} y1={Y(v)} y2={Y(v)} />
              <text className="uf-trend-axis" x={m.l - 8} y={Y(v)} textAnchor="end" dominantBaseline="central">{compact(v)}</text>
            </g>
          ))}
          {xTicks.map((i) => (
            <text key={i} className="uf-trend-axis" x={X(i)} y={H - 6} textAnchor={i === 0 && n > 2 ? 'start' : i === n - 1 && n > 2 ? 'end' : 'middle'}>{dayLabel(cur[i].date)}</text>
          ))}
          <g className="uf-reveal" key={style}>
          {style === 'flow' && first && n > 1 ? (
            <g style={colour(first.k)}>
              <path className="uf-trend-area" d={`${smoothPath(vals(first, cur).map((v, i) => ({ x: X(i), y: Y(v) })))}L${X(n - 1)},${Y(0)}L${X(0)},${Y(0)}Z`} />
            </g>
          ) : null}
          {shown.map((s, si) => {
            const line = (rows: Bucket[] | null): string => {
              const pts = vals(s, rows).map((v, i) => ({ x: X(i), y: Y(v) }));
              if (style === 'sharp') { return 'M' + pts.map((q) => q.x.toFixed(1) + ',' + q.y.toFixed(1)).join('L'); }
              if (style === 'steps') { return pts.map((q, i) => (i === 0 ? `M${q.x.toFixed(1)},${q.y.toFixed(1)}` : `H${q.x.toFixed(1)}V${q.y.toFixed(1)}`)).join(''); }
              return smoothPath(pts);
            };
            const gw = Math.min(26, (pw / Math.max(1, n)) * 0.72), sub = gw / Math.max(1, shown.length);
            const cx = (i: number) => X(i) - gw / 2 + sub * (si + 0.5);
            const v = vals(s, cur);
            return (
              <g key={s.key} style={colour(s.k)}>
                {prev ? <path className="uf-trend-prev" d={style === 'sharp' || style === 'steps' ? line(prev) : smoothPath(vals(s, prev).map((x, i) => ({ x: X(i), y: Y(x) })))} /> : null}
                {n > 1 && (style === 'flow' || style === 'sharp' || style === 'steps') ? <path className={'uf-trend-line' + (style === 'sharp' ? ' uf-trend-sharp' : '')} d={line(cur)} /> : null}
                {style === 'dots' ? v.map((x, i) => <circle key={i} className="uf-trend-pt" cx={X(i)} cy={Y(x)} r={n > 60 ? 2.2 : 3.4} />) : null}
                {style === 'bars' ? v.map((x, i) => <rect key={i} className="uf-trend-bar" x={cx(i) - sub * 0.45} width={Math.max(1.5, sub * 0.9)} y={Y(x)} height={Math.max(0, Y(0) - Y(x))} />) : null}
                {style === 'candles' ? v.map((x, i) => {
                  const o = i === 0 ? x : v[i - 1];
                  return <rect key={i} className={'uf-trend-candle ' + (x >= o ? 'uf-up' : 'uf-down')} x={cx(i) - sub * 0.4} width={Math.max(1.5, sub * 0.8)} y={Math.min(Y(x), Y(o))} height={Math.max(2, Math.abs(Y(x) - Y(o)))} />;
                }) : null}
                {n === 1 && style !== 'bars' && style !== 'candles' ? <circle className="uf-trend-dot" cx={X(0)} cy={Y(cur[0][s.key])} r={4} /> : null}
              </g>
            );
          })}
          </g>
          {at !== null ? (
            <g>
              <line className="uf-trend-cross" x1={X(at)} x2={X(at)} y1={m.t} y2={m.t + ph} />
              {shown.map((s) => <circle key={s.key} className="uf-trend-dot" style={colour(s.k)} cx={X(at)} cy={Y(cur[at][s.key])} r={4.5} />)}
            </g>
          ) : null}
        </svg>
        {at !== null ? (
          <div className="uf-trend-tip" aria-hidden="true" style={{ left: X(at), transform: X(at) > W / 2 ? 'translateX(calc(-100% - 14px))' : 'translateX(14px)' }}>
            <b>{label(cur[at])}</b>
            {shown.map((s) => (
              <div key={s.key} style={colour(s.k)}>
                <span><i />{s.label}</span>
                <span>{num(cur[at][s.key])}{prev && prev[at] ? <em>{delta(cur[at][s.key], prev[at][s.key])}</em> : null}</span>
              </div>
            ))}
            {prev && prev[at] ? <div><em>{p.compareLabel}: {label(prev[at])}</em></div> : null}
          </div>
        ) : null}
      </div>
      <div className="screen-reader-text" role="status" aria-live="polite">{sr}</div>
      <p className="uf-trend-note">
        {prev ? (
          <span className="uf-trend-key"><svg viewBox="0 0 22 8" aria-hidden="true"><path d="M0,4H22" stroke="currentColor" strokeWidth="1.75" strokeDasharray="5 4" opacity=".6" /></svg>{p.compareLabel}</span>
        ) : null}
        {style === 'candles' ? __('Each candle runs from the previous point to this one: filled means up, outlined means down.') + ' ' : ''}{size > 1 ? __('Grouped by week.') + ' ' : ''}{__('"Visitors (per day)" counts each visitor once per day, so its total is higher than the unique visitors above.')}
      </p>
      <details>
        <summary>{__('Show as table')}</summary>
        <div className="uf-scroll"><table className="widefat striped">
          <thead><tr><th>{__('Date')}</th><th>{__('Sessions')}</th><th>{__('Page views')}</th><th>{__('Visitors (per day)')}</th>{prev ? <th>{p.compareLabel}: {__('Page views')}</th> : null}</tr></thead>
          <tbody>{cur.map((r, i) => (
            <tr key={r.date}><td>{label(r)}</td><td>{num(r.sessions)}</td><td>{num(r.pageviews)}</td><td>{num(r.visitors)}</td>{prev ? <td>{prev[i] ? num(prev[i].pageviews) : '—'}</td> : null}</tr>
          ))}</tbody>
        </table></div>
      </details>
    </div>
  );
}

export interface RankRow { key: string; label: React.ReactNode; sub?: string; value: number; k?: number }

/** Ranked list with a bar behind each row, the value and its share of the total. Rows are a real list, so screen readers get plain text. */
export function BarList(p: { rows: RankRow[]; total: number; k?: number; valueLabel?: string; swatch?: boolean }) {
  const max = Math.max(1, ...p.rows.map((r) => r.value));
  return (
    <ul className="uf-bl">
      {p.rows.map((r) => (
        <li key={r.key} style={colour(r.k === undefined ? p.k || 1 : r.k)}>
          <span className="uf-bl-bar" style={{ width: Math.max(2, Math.round((100 * r.value) / max)) + '%' }} aria-hidden="true" />
          <span className="uf-bl-l">{p.swatch ? <i className="uf-bl-sw" aria-hidden="true" /> : null}{r.label}{r.sub ? <small>{r.sub}</small> : null}</span>
          <span className="uf-bl-v"><b>{num(r.value)}</b><span>{share(r.value, p.total)}</span></span>
        </li>
      ))}
    </ul>
  );
}

export interface DonutItem { key: string; label: string; value: number; k: number }

/** Share-of-total ring with a legend list; the figures are in the list, the ring is the picture. */
export function Donut(p: { items: DonutItem[]; centre: string; centreLabel: string }) {
  const total = p.items.reduce((a, i) => a + i.value, 0);
  const arcs = donutArcs(p.items.map((i) => i.value));
  const gap = p.items.filter((i) => i.value > 0).length > 1 ? 0.02 : 0;
  return (
    <div className="uf-donut">
      <svg viewBox="0 0 132 132" aria-hidden="true" focusable="false">
        {p.items.map((it, i) => (arcs[i].a1 - arcs[i].a0 > 0 ? <path key={it.key} className="uf-donut-seg" style={colour(it.k)} d={arcPath(66, 66, 40, 62, arcs[i].a0 + gap, arcs[i].a1 - gap)} /> : null))}
        <text className="uf-donut-t" x="66" y="62">{p.centre}</text>
        <text className="uf-donut-s" x="66" y="80">{p.centreLabel}</text>
      </svg>
      <ul>
        {p.items.map((it) => (
          <li key={it.key} style={colour(it.k)}>
            <span><i className="uf-bl-sw" aria-hidden="true" />{it.label}</span>
            <span><b>{num(it.value)}</b> <span className="uf-bl-v"><span>{share(it.value, total)}</span></span></span>
          </li>
        ))}
      </ul>
    </div>
  );
}

export { Fragment };
