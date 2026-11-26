import { h, Fragment, __, cfg, can, upgradeUrl } from '../wp';
import type { Query } from '../hooks';
import type { SessionState } from '../types';
import type * as React from 'react';

/** Change against the previous period; the arrow and words carry the meaning, colour is only a hint. */
export function Delta(p: { now: number; before: number | null | undefined; invert?: boolean }) {
  if (p.before === null || p.before === undefined) { return null; }
  if (p.before === 0) { return p.now === 0 ? <span className="uf-delta">{__('no change')}</span> : <span className="uf-delta uf-delta-up">{__('new vs. previous period')}</span>; }
  const pct = Math.round(((p.now - p.before) / p.before) * 100);
  if (pct === 0) { return <span className="uf-delta">{__('no change')} <span className="screen-reader-text">{__('vs. previous period')}</span></span>; }
  const up = pct > 0;
  const good = p.invert ? !up : up;
  return (
    <span className={'uf-delta ' + (good ? 'uf-delta-good' : 'uf-delta-bad')}>
      <span aria-hidden="true">{up ? '▲' : '▼'}</span> {Math.abs(pct)}% <span className="screen-reader-text">{up ? __('up') : __('down')} {__('vs. previous period')}</span>
    </span>
  );
}

/** Change against the comparison period as a small chip. `vs` names the comparison for screen readers ("vs. previous period"). */
export function DeltaChip(p: { now: number; before: number | null | undefined; invert?: boolean; vs: string }) {
  if (p.before === null || p.before === undefined) { return null; }
  if (p.before === 0) { return <span className="uf-dchip">{p.now === 0 ? __('no change') : __('new')}<span className="screen-reader-text"> {p.vs}</span></span>; }
  const pct = Math.round(((p.now - p.before) / p.before) * 100);
  if (pct === 0) { return <span className="uf-dchip">{__('no change')}<span className="screen-reader-text"> {p.vs}</span></span>; }
  const up = pct > 0;
  const good = p.invert ? !up : up;
  return (
    <span className={'uf-dchip ' + (good ? 'is-good' : 'is-bad')}>
      <span aria-hidden="true">{up ? '▲' : '▼'}</span>{Math.abs(pct)}%<span className="screen-reader-text"> {up ? __('up') : __('down')} {p.vs}</span>
    </span>
  );
}

/** Headline figure with its change chip and an optional trend line. `k` picks the accent colour (--uf-series-k). */
export function Kpi(p: { label: string; value: string; hint?: string; chip?: React.ReactNode; spark?: React.ReactNode; k: number }) {
  return (
    <div className="uf-kpi" style={{ ['--k' as string]: `var(--uf-series-${p.k})` } as React.CSSProperties}>
      <div className="uf-kpi-l">{p.label}</div>
      <div className="uf-kpi-v">{p.value}</div>
      <div className="uf-kpi-row"><div>{p.chip}{p.hint ? <div className="uf-kpi-h">{p.hint}</div> : null}</div>{p.spark}</div>
    </div>
  );
}

export function Stat(p: { label: string; value: string; hint?: string; small?: boolean; delta?: React.ReactNode }) {
  return (
    <div className="uf-stat">
      <div className={'uf-stat-v' + (p.small ? ' uf-stat-sm' : '')}>{p.value}</div>
      <div className="uf-stat-l">{p.label}</div>
      {p.delta ? <div className="uf-stat-d">{p.delta}</div> : null}
      {p.hint ? <div className="uf-stat-h">{p.hint}</div> : null}
    </div>
  );
}

/** A box title that is a button: each click moves this box to its next view (list, pie, donut ...). `view` is the name of the view now shown. */
export function CycleTitle(p: { title: string; view: string; onCycle: () => void }) {
  return (
    <h2 className="uf-cycle-h">
      <button type="button" className="uf-cycle" onClick={p.onCycle} aria-label={`${p.title}. ${__('Change how this box is shown. Now showing:')} ${p.view}`}>
        <span>{p.title}</span><span className="uf-cycle-k" aria-hidden="true">{p.view}</span>
      </button>
    </h2>
  );
}

export function Card(p: { title?: string; children?: React.ReactNode; className?: string; id?: string; more?: { href: string; label?: string }; cycle?: { view: string; onCycle: () => void } }) {
  const heading = p.title && p.cycle ? <CycleTitle title={p.title} view={p.cycle.view} onCycle={p.cycle.onCycle} /> : p.title ? <h2>{p.title}</h2> : null;
  return (
    <section className={'uf-card ' + (p.className || '')} id={p.id}>
      {p.title && p.more && p.more.href ? <div className="uf-card-head">{heading}<a className="uf-card-more" href={p.more.href}>{p.more.label || __('View all')} <span aria-hidden="true">→</span></a></div> : heading}
      {p.children}
    </section>
  );
}

/** Quiet "this is a display limit" note with an upgrade link; renders nothing for Pro. */
export function LimitNote(p: { feature: string; children: React.ReactNode }) {
  if (can(p.feature) || !upgradeUrl()) { return null; }
  return <p className="uf-limit-note">{p.children} <a href={upgradeUrl()} target="_blank" rel="noopener noreferrer">{__('Upgrade to Pro')}</a></p>;
}

export function Empty(p: { children?: React.ReactNode }) {
  return <p className="uf-empty">{p.children}</p>;
}

/** Renders loading / error / data states for a query. */
export function Async<T>(p: { q: Query<T>; children: (d: T) => React.ReactNode; empty?: (d: T) => boolean; emptyText?: string }) {
  const { data, error, loading, reload } = p.q;
  if (error) {
    return (
      <div className="notice notice-error inline" role="alert">
        <p>{__('Could not load data:')} {error.message} <button className="button" onClick={reload}>{__('Retry')}</button></p>
      </div>
    );
  }
  if (data === null) {
    return <p className="uf-loading" role="status" aria-live="polite">{loading ? __('Loading…') : ''}</p>;
  }
  if (p.empty && p.empty(data)) {
    return <Empty>{p.emptyText || __('Nothing recorded yet.')}</Empty>;
  }
  return <div className={loading ? 'uf-stale' : ''} aria-busy={loading}>{p.children(data)}</div>;
}

export function Badge(p: { kind?: 'user' | 'ret' | 'live' | 'obs' | 'est' | ''; children?: React.ReactNode }) {
  return <span className={'uf-badge' + (p.kind ? ' uf-badge-' + p.kind : '')}>{p.children}</span>;
}

export function StateBadge(p: { state: SessionState }) {
  const map: Record<SessionState, [string, string]> = {
    active: ['live', __('Active')],
    ended_estimated: ['est', __('Ended (estimated)')],
    logged_out_observed: ['obs', __('Logged out (observed)')],
  };
  const [k, l] = map[p.state];
  return <Badge kind={k as 'live' | 'est' | 'obs'}>{l}</Badge>;
}

export function Pager(p: { page: number; total: number; perPage: number; onPage: (n: number) => void }) {
  const pages = Math.max(1, Math.ceil(p.total / p.perPage));
  if (pages <= 1) { return null; }
  return (
    <nav className="uf-pager" aria-label={__('Pagination')}>
      <span>{`${p.page} / ${pages} (${p.total})`}</span>{' '}
      <button className="button" disabled={p.page <= 1} onClick={() => p.onPage(p.page - 1)}>{'« ' + __('Previous')}</button>{' '}
      <button className="button" disabled={p.page >= pages} onClick={() => p.onPage(p.page + 1)}>{__('Next') + ' »'}</button>
    </nav>
  );
}

export const link = (href: string, children: React.ReactNode, cls?: string) => <a href={href} className={cls}>{children}</a>;
export { Fragment };

/** Download link for a PDF report (server-generated, bounded). */
export function PdfLink(p: { params: Record<string, string | number>; label?: string }) {
  const c = cfg();
  if (!c.pdfNonce) return null; // The Reports module is off: the server has no PDF handler to call.
  const q = Object.keys(p.params).map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(String(p.params[k]))}`).join('&');
  return <a className="button" href={`${c.adminPost}?action=userflo_pdf&${q}&_wpnonce=${encodeURIComponent(c.pdfNonce)}`}>{p.label || __('Download PDF')}</a>;
}
