import { h, __, useState, useEffect, can, modOn, sprintf } from '../wp';
import { useApi } from '../hooks';
import type { AuditPages, AuditSummary } from '../types';
import { dateTime, num } from '../format';
import { Async, Empty, LimitNote, Pager, Stat } from '../components/ui';
import { HealthDot } from '../components/Health';
import { slots } from '../slots';
import { A11yOverview } from '../components/Accessibility';

const PER = 25;
const BUCKETS: ['' | 'red' | 'amber' | 'green' | 'unscanned', string][] = [['', __('All')], ['red', __('Issues')], ['amber', __('Warnings')], ['green', __('No issues')], ['unscanned', __('Not scanned')]];

/** Page health: how the website audit sees each page. Lite shows the basic checks; Pro adds the prioritised list and findings by kind. */
export function PageHealth(p: { days: number; onOpen: (url: string) => void }) {
  const [bucket, setBucket] = useState<'' | 'red' | 'amber' | 'green' | 'unscanned'>('');
  const [input, setInput] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  useEffect(() => { const t = setTimeout(() => { setSearch(input.trim()); setPage(1); }, 300); return () => clearTimeout(t); }, [input]);
  const sum = useApi<AuditSummary>('audit/summary');
  const list = useApi<AuditPages>('audit/pages', { health: bucket, search, days: p.days, page, per_page: PER });
  if (!modOn('audit')) { return <Empty>{__('The website audit is turned off in Settings → Components.')}</Empty>; }
  const Full = can('page_audit') && modOn('page_intelligence') ? slots().PageIntelligence : undefined;
  return (
    <div>
      <p className="uf-sub">{__('UserFlo reads your own pages a few at a time, as a visitor would see them, and checks what it can. It never changes anything. A page is red when a verified issue was found, amber for warnings only.')}</p>
      <Async q={sum}>
        {(s) => (
          <div>
            <div className="uf-stats">
              <Stat label={__('pages scanned')} value={num(s.pages.ok + s.pages.error)} />
              <Stat label={__('with issues')} value={num(s.health.red)} />
              <Stat label={__('with warnings')} value={num(s.health.amber)} />
              <Stat label={__('with no issues')} value={num(s.health.green)} />
              <Stat label={__('waiting or unreadable')} value={num(s.pages.queued + s.pages.skipped)} />
            </div>
            <p className="uf-sub">
              {s.last_scan ? sprintf(__('Last scan: %s.'), dateTime(s.last_scan + ' UTC')) : __('Nothing scanned yet; the first pages are read within the hour.')} {s.due ? sprintf(__('%d pages are waiting.'), s.due) : ''}
              {s.cap ? ' ' + sprintf(__('This edition keeps up to %d pages in the inventory.'), s.cap) : ''}
            </p>
            {s.seo.both ? <div className="notice notice-warning inline" role="status"><p>{sprintf(__('%s are both active. Their tags may duplicate each other; UserFlo does not decide which is right, so please review the configuration.'), s.seo.names.join(' + '))}</p></div> : null}
          </div>
        )}
      </Async>
      {sum.data && sum.data.a11y ? <A11yOverview a11y={sum.data.a11y} /> : null}
      {Full ? <Full days={p.days} onOpen={p.onOpen} /> : <LimitNote feature="page_audit">{__('Pro adds a prioritised list of pages that need attention, every kind of finding, and a report for each page that joins the audit with visits and journeys.')}</LimitNote>}
      <div className="uf-filter" role="group" aria-label={__('Show pages')}>
        {BUCKETS.map(([k, l]) => <button key={k} type="button" className={'button' + (bucket === k ? ' button-primary' : '')} aria-pressed={bucket === k} onClick={() => { setBucket(k); setPage(1); }}>{l}</button>)}
        <label className="screen-reader-text" htmlFor="uf-ph-search">{__('Search pages')}</label>
        <input id="uf-ph-search" type="search" className="regular-text" placeholder={__('Search URL or title…')} value={input} onInput={(e) => setInput((e.target as HTMLInputElement).value)} />
      </div>
      <Async q={list} empty={(d) => d.items.length === 0} emptyText={__('No pages match.')}>
        {(d) => (
          <div>
            <div className="uf-scroll"><table className="widefat striped uf-table">
              <thead><tr><th scope="col">{__('Page')}</th><th scope="col">{__('Health')}</th><th scope="col">{__('Issues')}</th><th scope="col">{__('Warnings')}</th><th scope="col">{__('Views')}</th><th scope="col">{__('Elements')}</th><th scope="col">{__('Scanned')}</th></tr></thead>
              <tbody>{d.items.map((r) => (
                <tr key={r.url}>
                  <td><button type="button" className="button-link uf-strong" onClick={() => p.onOpen(r.url)}><code>{r.url}</code></button>{r.title ? <div><small>{r.title}</small></div> : null}</td>
                  <td><HealthDot url={r.url} health={{ state: r.state, level: r.level, red: r.red, amber: r.amber, excluded: r.excluded, findings: r.findings }} /></td>
                  <td>{r.level < 0 ? '—' : <a href={'#/p/' + encodeURIComponent(r.url)} title={__('Open the page audit')}>{num(r.red)}</a>}</td><td>{r.level < 0 ? '—' : num(r.amber)}</td><td>{num(r.views)}</td><td>{r.elements ? num(r.elements) : '—'}</td>
                  <td>{r.scanned_at ? dateTime(r.scanned_at + ' UTC') : '—'}</td>
                </tr>))}</tbody>
            </table></div>
            <Pager page={page} total={d.total} perPage={PER} onPage={setPage} />
          </div>
        )}
      </Async>
    </div>
  );
}
