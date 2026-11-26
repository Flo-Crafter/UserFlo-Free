import { h, __, useState } from '../wp';
import type * as React from 'react';
import { Card } from './ui';
import { VizBody, vizLabel } from './Viz';
import { CountryMap } from './CountryMap';
import type { MapRow } from './CountryMap';
import type { VizKind, VizRow } from './Viz';

export type CycleView = 'list' | 'map' | VizKind;
const PREFIX = 'userflo_cycle_v1_';
const DEFAULT_ORDER: CycleView[] = ['list', 'pie', 'donut', 'columns', 'stack', 'rose', 'radar'];

const viewLabel = (v: CycleView): string => (v === 'list' ? __('List') : v === 'map' ? __('Map') : vizLabel(v));

/** The saved view of one box (this browser only; losing it just brings back the first view). */
export function savedView(id: string, views: CycleView[]): CycleView {
  try { const v = window.localStorage.getItem(PREFIX + id) as CycleView | null; if (v && views.indexOf(v) > -1) { return v; } } catch (e) { /* storage unavailable */ }
  return views[0];
}

/**
 * A box whose title cycles through ways of showing the same figures. The first view is the one the box always had (usually the list);
 * every click on the title moves to the next, and the last goes back to the first. Each box remembers its own view, and clicking one
 * never changes another. The figures stay readable in every view (the list, or the chart's legend and accessible name).
 */
export function CycleCard(p: {
  id: string; title: string; list: React.ReactNode; rows: VizRow[]; more?: { href: string; label?: string }; className?: string;
  order?: CycleView[]; mapRows?: MapRow[]; mapUnit?: string; head?: React.ReactNode; note?: React.ReactNode; centre?: string; centreLabel?: string;
}) {
  const positive = p.rows.filter((r) => r.value > 0).length;
  const views = (p.order || (p.mapRows ? (['list', 'map'] as CycleView[]).concat(DEFAULT_ORDER.slice(1)) : DEFAULT_ORDER)).filter((v) => v === 'list' || (v === 'map' && !!p.mapRows && p.mapRows.length > 0) || (positive > 0 && (v !== 'radar' || positive >= 3) && (v !== 'stack' || positive <= 6)));
  const [view, setView] = useState<CycleView>(() => savedView(p.id, views));
  const cur: CycleView = views.indexOf(view) > -1 ? view : views[0];
  const next = () => {
    const n = views[(views.indexOf(cur) + 1) % views.length];
    setView(n);
    try { window.localStorage.setItem(PREFIX + p.id, n); } catch (e) { /* ignore */ }
  };
  return (
    <Card title={p.title} more={p.more} className={p.className} cycle={views.length > 1 ? { view: viewLabel(cur), onCycle: next } : undefined}>
      {p.head}
      {cur === 'list' ? p.list : cur === 'map' ? <CountryMap rows={p.mapRows || []} unit={p.mapUnit || ''} /> : <VizBody kind={cur} rows={p.rows} centre={p.centre} centreLabel={p.centreLabel} />}
      {p.note}
    </Card>
  );
}
