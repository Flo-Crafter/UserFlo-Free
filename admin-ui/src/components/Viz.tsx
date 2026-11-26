import { h, __, useState, Fragment } from '../wp';
import type * as React from 'react';
import { num } from '../format';
import { arcPath, donutArcs, radarPoints, roseWedges, share, spoke } from '../chartMath';

export interface VizRow { key: string; label: string; value: number }
export type VizKind = 'bars' | 'donut' | 'pie' | 'radar' | 'rose' | 'columns' | 'stack';

const KINDS: [VizKind, string][] = [['bars', __('Bars')], ['donut', __('Donut')], ['pie', __('Pie')], ['radar', __('Star')], ['rose', __('Rose')], ['columns', __('Columns')], ['stack', __('Stacked bar')]];
export const vizLabel = (k: VizKind): string => (KINDS.find(([v]) => v === k) || KINDS[0])[1];
const colourOf = (i: number): React.CSSProperties => ({ ['--k' as string]: `var(--uf-series-${(i % 6) + 1})` } as React.CSSProperties);
const PREFIX = 'userflo_viz_v1_';
const MAX_SLICES = 8;

function stored(key: string, d: VizKind): VizKind {
  try { const v = window.localStorage.getItem(PREFIX + key) as VizKind | null; if (v && KINDS.some(([k]) => k === v)) { return v; } } catch (e) { /* storage unavailable */ }
  return d;
}

/** The smallest rows beyond the first MAX_SLICES - 1 are folded into "Other" so rings and stars stay readable. */
export function fold(rows: VizRow[]): VizRow[] {
  const r = rows.filter((x) => x.value > 0).sort((a, b) => b.value - a.value);
  if (r.length <= MAX_SLICES) { return r; }
  const rest = r.slice(MAX_SLICES - 1);
  return r.slice(0, MAX_SLICES - 1).concat({ key: '_other', label: __('Other'), value: rest.reduce((a, x) => a + x.value, 0) });
}

function Legend(p: { rows: VizRow[]; total: number }) {
  return (
    <ul className="uf-viz-legend">
      {p.rows.map((r, i) => (
        <li key={r.key} style={colourOf(i)}><span title={r.label}><i className="uf-bl-sw" aria-hidden="true" /><span className="uf-viz-lt">{r.label}</span></span><span><b>{num(r.value)}</b> <span className="uf-bl-v"><span>{share(r.value, p.total)}</span></span></span></li>
      ))}
    </ul>
  );
}

function Ring(p: { rows: VizRow[]; total: number; hole: number; centre?: string; centreLabel?: string }) {
  const arcs = donutArcs(p.rows.map((r) => r.value));
  const gap = p.rows.length > 1 ? 0.02 : 0;
  return (
    <svg viewBox="0 0 132 132" className="uf-viz-svg" role="img" aria-label={p.rows.map((r) => `${r.label}: ${num(r.value)}`).join(', ')}>
      {p.rows.map((r, i) => (arcs[i].a1 - arcs[i].a0 > 0 ? <path key={r.key} className="uf-donut-seg" style={colourOf(i)} d={arcPath(66, 66, p.hole, 62, arcs[i].a0 + gap, arcs[i].a1 - gap)}><title>{`${r.label}: ${num(r.value)} (${share(r.value, p.total)})`}</title></path> : null))}
      {p.centre ? <Fragment><text className="uf-donut-t" x="66" y="62">{p.centre}</text><text className="uf-donut-s" x="66" y="80">{p.centreLabel}</text></Fragment> : null}
    </svg>
  );
}

function Radar(p: { rows: VizRow[]; total: number }) {
  const n = p.rows.length, cx = 100, cy = 100, R = 66;
  const max = Math.max(1, ...p.rows.map((r) => r.value));
  const pts = radarPoints(p.rows.map((r) => r.value), cx, cy, R, max);
  const poly = pts.map((q) => q.x.toFixed(1) + ',' + q.y.toFixed(1)).join(' ');
  return (
    <svg viewBox="0 0 200 200" className="uf-viz-svg uf-viz-radar" role="img" aria-label={p.rows.map((r) => `${r.label}: ${num(r.value)}`).join(', ')}>
      {[0.25, 0.5, 0.75, 1].map((f) => <polygon key={f} className="uf-viz-ring" points={p.rows.map((_, i) => { const s = spoke(i, n, cx, cy, R * f); return s.x.toFixed(1) + ',' + s.y.toFixed(1); }).join(' ')} />)}
      {p.rows.map((r, i) => { const s = spoke(i, n, cx, cy, R), l = spoke(i, n, cx, cy, R + 14); return (
        <Fragment key={r.key}>
          <line className="uf-viz-axis" x1={cx} y1={cy} x2={s.x} y2={s.y} />
          <text className="uf-viz-lab" x={l.x} y={l.y} textAnchor={l.x < cx - 4 ? 'end' : l.x > cx + 4 ? 'start' : 'middle'} dominantBaseline="middle">{r.label.length > 14 ? r.label.slice(0, 13) + '…' : r.label}</text>
        </Fragment>); })}
      <polygon className="uf-viz-poly" points={poly} />
      {pts.map((q, i) => <circle key={p.rows[i].key} className="uf-viz-dot" cx={q.x} cy={q.y} r="3.4"><title>{`${p.rows[i].label}: ${num(p.rows[i].value)} (${share(p.rows[i].value, p.total)})`}</title></circle>)}
    </svg>
  );
}

function Rose(p: { rows: VizRow[]; total: number }) {
  const w = roseWedges(p.rows.map((r) => r.value), 8, 62);
  return (
    <svg viewBox="0 0 132 132" className="uf-viz-svg" role="img" aria-label={p.rows.map((r) => `${r.label}: ${num(r.value)}`).join(', ')}>
      <circle className="uf-viz-ring" cx="66" cy="66" r="62" /><circle className="uf-viz-ring" cx="66" cy="66" r="35" />
      {p.rows.map((r, i) => <path key={r.key} className="uf-donut-seg" style={colourOf(i)} d={arcPath(66, 66, 8, w[i].r, w[i].a0 + 0.015, w[i].a1 - 0.015)}><title>{`${r.label}: ${num(r.value)} (${share(r.value, p.total)})`}</title></path>)}
    </svg>
  );
}

function Columns(p: { rows: VizRow[]; total: number }) {
  const max = Math.max(1, ...p.rows.map((r) => r.value));
  return (
    <div className="uf-viz-cols" role="img" aria-label={p.rows.map((r) => `${r.label}: ${num(r.value)}`).join(', ')}>
      {p.rows.map((r, i) => (
        <div key={r.key} className="uf-viz-col" style={colourOf(i)} title={`${r.label}: ${num(r.value)} (${share(r.value, p.total)})`}>
          <span className="uf-viz-colv">{num(r.value)}</span>
          <span className="uf-viz-colbar" style={{ height: Math.max(3, (r.value / max) * 100) + '%' }} />
          <span className="uf-viz-coll">{r.label}</span>
        </div>
      ))}
    </div>
  );
}

function Stack(p: { rows: VizRow[]; total: number }) {
  return (
    <div className="uf-viz-stack" role="img" aria-label={p.rows.map((r) => `${r.label}: ${share(r.value, p.total)}`).join(', ')}>
      {p.rows.map((r, i) => <span key={r.key} style={{ ...colourOf(i), width: (100 * r.value) / Math.max(1, p.total) + '%' }} title={`${r.label}: ${num(r.value)} (${share(r.value, p.total)})`}>{(100 * r.value) / Math.max(1, p.total) >= 12 ? share(r.value, p.total) : ''}</span>)}
    </div>
  );
}

function Bars(p: { rows: VizRow[]; total: number }) {
  const max = Math.max(1, ...p.rows.map((r) => r.value));
  return (
    <ul className="uf-bl">
      {p.rows.map((r, i) => (
        <li key={r.key} style={colourOf(i)}>
          <span className="uf-bl-bar" style={{ width: Math.max(2, Math.round((100 * r.value) / max)) + '%' }} aria-hidden="true" />
          <span className="uf-bl-l"><i className="uf-bl-sw" aria-hidden="true" />{r.label}</span>
          <span className="uf-bl-v"><b>{num(r.value)}</b><span>{share(r.value, p.total)}</span></span>
        </li>
      ))}
    </ul>
  );
}

/** The picture for one chart type, without the type switch. Used by Viz and by the overview boxes whose title cycles through the types. */
export function VizBody(p: { kind: VizKind; rows: VizRow[]; centre?: string; centreLabel?: string }) {
  const rows = fold(p.rows);
  const total = rows.reduce((a, r) => a + r.value, 0);
  const k = p.kind;
  return (
    <div className="uf-viz">
      {k === 'bars' ? <Bars rows={rows} total={total} /> : null}
      {k === 'columns' ? <Columns rows={rows} total={total} /> : null}
      {k === 'stack' ? <Fragment><Stack rows={rows} total={total} /><Legend rows={rows} total={total} /></Fragment> : null}
      {k === 'donut' || k === 'pie' || k === 'radar' || k === 'rose' ? (
        <div className="uf-viz-body">
          {k === 'donut' ? <Ring rows={rows} total={total} hole={40} centre={p.centre || num(total)} centreLabel={p.centreLabel || __('total')} /> : null}
          {k === 'pie' ? <Ring rows={rows} total={total} hole={0} /> : null}
          {k === 'radar' ? <Radar rows={rows} total={total} /> : null}
          {k === 'rose' ? <Rose rows={rows} total={total} /> : null}
          <Legend rows={rows} total={total} />
        </div>
      ) : null}
    </div>
  );
}

/**
 * One data set, several ways to look at it. The viewer's choice per panel is remembered in this browser only. The figures are always in
 * a list (or the chart's accessible name), the picture is an addition: a star needs three or more rows, a stacked bar suits two to five.
 */
export function Viz(p: { id: string; rows: VizRow[]; initial?: VizKind; kinds?: VizKind[]; centre?: string; centreLabel?: string; empty?: string; fixed?: VizKind }) {
  const [kind, setKind] = useState<VizKind>(() => p.fixed || stored(p.id, p.initial || 'donut'));
  const rows = fold(p.rows);
  const total = rows.reduce((a, r) => a + r.value, 0);
  if (!rows.length) { return <p className="uf-empty">{p.empty || __('No data.')}</p>; }
  const allowed = (p.kinds || KINDS.map(([k]) => k)).filter((k) => (k !== 'radar' || rows.length >= 3) && (k !== 'stack' || rows.length <= 6));
  const k: VizKind = allowed.indexOf(kind) > -1 ? kind : allowed[0];
  const pick = (v: VizKind) => { setKind(v); try { window.localStorage.setItem(PREFIX + p.id, v); } catch (e) { /* ignore */ } };
  return (
    <div className="uf-viz">
      {!p.fixed && allowed.length > 1 ? (
        <div className="uf-viz-switch" role="group" aria-label={__('Chart type')}>
          {allowed.map((v) => <button key={v} type="button" className={'button button-small' + (k === v ? ' button-primary' : '')} aria-pressed={k === v} onClick={() => pick(v)}>{vizLabel(v)}</button>)}
        </div>
      ) : null}
      {k === 'bars' ? <Bars rows={rows} total={total} /> : null}
      {k === 'columns' ? <Columns rows={rows} total={total} /> : null}
      {k === 'stack' ? <Fragment><Stack rows={rows} total={total} /><Legend rows={rows} total={total} /></Fragment> : null}
      {k === 'donut' || k === 'pie' || k === 'radar' || k === 'rose' ? (
        <div className="uf-viz-body">
          {k === 'donut' ? <Ring rows={rows} total={total} hole={40} centre={p.centre || num(total)} centreLabel={p.centreLabel || __('total')} /> : null}
          {k === 'pie' ? <Ring rows={rows} total={total} hole={0} /> : null}
          {k === 'radar' ? <Radar rows={rows} total={total} /> : null}
          {k === 'rose' ? <Rose rows={rows} total={total} /> : null}
          <Legend rows={rows} total={total} />
        </div>
      ) : null}
    </div>
  );
}
