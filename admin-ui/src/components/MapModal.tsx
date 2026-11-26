import { h, __, useEffect, useRef } from '../wp';
import { useApi } from '../hooks';
import type { GeoDetails } from '../types';
import { dateOnly, num } from '../format';
import { Async } from './ui';

const DAYS = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];

function Bars(p: { values: number[]; labels: string[]; title: string }) {
  const max = Math.max(1, ...p.values);
  return (
    <figure className="uf-geo-bars">
      <div className="uf-geo-bars-in" role="img" aria-label={p.title}>
        {p.values.map((v, i) => <div key={i} className="uf-geo-bar" title={`${p.labels[i]}: ${v}`}><span style={{ height: Math.max(2, (v / max) * 100) + '%' }} /></div>)}
      </div>
      <div className="uf-geo-bars-lab">{p.labels.map((l, i) => <span key={i}>{l}</span>)}</div>
      <figcaption>{p.title}</figcaption>
    </figure>
  );
}

export function MapModal(p: { level: string; keyId: string; title: string; days: number; onClose: () => void }) {
  const q = useApi<GeoDetails>('geo/details', { level: p.level, key: p.keyId, days: p.days });
  const box = useRef<HTMLDivElement | null>(null);
  useEffect(() => {
    const prev = document.activeElement as HTMLElement | null;
    if (box.current) { box.current.focus(); }
    const key = (e: KeyboardEvent) => { if (e.key === 'Escape') { p.onClose(); } };
    document.addEventListener('keydown', key);
    document.body.classList.add('uf-modal-open');
    return () => { document.removeEventListener('keydown', key); document.body.classList.remove('uf-modal-open'); if (prev) { prev.focus(); } };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return (
    <div className="uf-modal-bg" onClick={(e) => { if (e.target === e.currentTarget) { p.onClose(); } }}>
      <div className="uf-modal uf-tier-silver" role="dialog" aria-modal="true" aria-labelledby="uf-geo-title" tabIndex={-1} ref={box}>
        <div className="uf-modal-top">
          <div><h2 id="uf-geo-title">{p.title}</h2><p className="uf-ach-cat">{__('Last')} {p.days} {__('days')}</p></div>
          <button className="button" onClick={p.onClose} aria-label={__('Close')}>×</button>
        </div>
        <Async q={q}>
          {(d) => (
            <div>
              <p><strong>{num(d.visitors)}</strong> {__('visitors')} · <strong>{num(d.sessions)}</strong> {__('sessions')}</p>
              <div className="uf-cols">
                <section>
                  <h3>{__('Top pages')}</h3>
                  {d.pages.length ? <ol className="uf-geo-list">{d.pages.map((x) => <li key={x.url}><code>{x.url}</code> <strong>{num(x.views)}</strong></li>)}</ol> : <p className="uf-empty">{__('No page views.')}</p>}
                </section>
                <section>
                  <h3>{__('Top logged-in users')}</h3>
                  {d.users.length ? (
                    <ol className="uf-geo-list">{d.users.map((u) => <li key={u.visitor_id}><a href={`${window.userfloApp.urls.visitors}#/visitor/${u.visitor_id}`} className="uf-strong">{u.label}</a> <strong>{num(u.sessions)}</strong> {__('sessions')}</li>)}</ol>
                  ) : <p className="uf-empty">{__('No logged-in users from here.')}</p>}
                </section>
              </div>
              <Bars values={d.hours} labels={d.hours.map((_v, i) => String(i))} title={__('Visits by hour of day (site time)')} />
              <Bars values={d.weekdays} labels={DAYS} title={__('Visits by day of week')} />
              <div className="uf-cols">
                <section>
                  <h3>{__('Top times of day')}</h3>
                  {d.top_hours.length ? <p>{d.top_hours.map((x) => `${String(x).padStart(2, '0')}:00–${String(x).padStart(2, '0')}:59`).join(', ')}</p> : <p className="uf-empty">—</p>}
                  <h3>{__('Top days')}</h3>
                  <ol className="uf-geo-list">{d.top_days.map((x) => <li key={x.date}>{dateOnly(x.date + 'T00:00:00Z')} <strong>{num(x.sessions)}</strong></li>)}</ol>
                </section>
                <section>
                  <h3>{__('Top weeks')}</h3>
                  <ol className="uf-geo-list">{d.top_weeks.map((x) => <li key={x.week}>{x.week} <small>({dateOnly(x.starts + 'T00:00:00Z')})</small> <strong>{num(x.sessions)}</strong></li>)}</ol>
                </section>
              </div>
            </div>
          )}
        </Async>
      </div>
    </div>
  );
}
