import { h, __, useState, useEffect, rangePresets, modOn } from '../wp';
import { useApi } from '../hooks';
import type { PageListRow, PageVisits } from '../types';
import { dateTime, duration, num } from '../format';
import { Async, Badge, Pager } from '../components/ui';
import { ColumnMenu, SortTh, sortRows, useColumns } from '../components/table';
import { ExcludeButton } from '../components/Exclude';
import { HealthDot } from '../components/Health';
import { PageAudit } from '../components/PageAudit';
import { PageHealth } from './PageHealth';
import type { Col, Sort } from '../components/table';

const PER = 25;
/** "Highlight AI and bot visits" is remembered in this browser only. */
const HL_KEY = 'userflo_pg_bots';
const readHl = (): boolean => { try { return window.localStorage.getItem(HL_KEY) !== '0'; } catch (e) { return true; } };
const COLS: Col[] = [{ id: 'page', label: __('Page'), fixed: true }, ...(modOn('audit') ? [{ id: 'health', label: __('Health') }] : []), { id: 'views', label: __('Views') }, { id: 'visitors', label: __('Visitors') }, { id: 'avg_engaged', label: __('Avg. engaged') }];
const visitorLink = (id: number, sid: number) => `${window.userfloApp.urls.visitors}#/visitor/${id}/session/${sid}`;

export function parsePageHash(hash: string): string | null {
  const m = /^#\/p\/(.+)$/.exec(hash);
  if (!m) { return null; }
  try { const u = decodeURIComponent(m[1]); return u.charAt(0) === '/' ? u : null; } catch (e) { return null; }
}

function PageList(p: { days: number; setDays: (n: number) => void; onOpen: (url: string) => void }) {
  const [input, setInput] = useState('');
  const [search, setSearch] = useState('');
  useEffect(() => { const t = setTimeout(() => setSearch(input.trim()), 300); return () => clearTimeout(t); }, [input]);
  const cols = useColumns('pages', COLS);
  const [sort, setSort] = useState<Sort>({ by: 'views', dir: 'desc' });
  const [hl, setHl] = useState(readHl);
  const [onlyBots, setOnlyBots] = useState(false);
  const q = useApi<{ items: PageListRow[] }>('pages', { days: p.days, search, bots: hl ? 1 : 0 });
  const presets = rangePresets([[1, __('24 hours')], [7, __('7 days')], [30, __('30 days')], [90, __('90 days')]]);
  return (
    <div>
      <h1 className="uf-title">{__('Pages')}</h1>
      <p className="uf-sub">{__('Pick a page to see everyone who viewed it. Shows the 200 most viewed pages in the period.')}</p>
      <div className="uf-filter">
        {presets.map(([d, l]) => <button key={d} className={'button' + (p.days === d ? ' button-primary' : '')} onClick={() => p.setDays(d)}>{l}</button>)}
        <label className="screen-reader-text" htmlFor="uf-pg-search">{__('Search pages')}</label>
        <input id="uf-pg-search" type="search" className="regular-text" placeholder={__('Search URL or title…')} value={input} onInput={(e) => setInput((e.target as HTMLInputElement).value)} />
        <label><input type="checkbox" checked={hl} onChange={(e) => { const on = (e.target as HTMLInputElement).checked; setHl(on); if (!on) { setOnlyBots(false); } try { window.localStorage.setItem(HL_KEY, on ? '1' : '0'); } catch (x) { /* storage unavailable */ } }} /> {__('Highlight AI and bot visits')}</label>
        {hl ? <label><input type="checkbox" checked={onlyBots} onChange={(e) => setOnlyBots((e.target as HTMLInputElement).checked)} /> {__('Only pages bots requested')}</label> : null}
      </div>
      <Async q={q} empty={(d) => d.items.length === 0} emptyText={__('No pages match.')}>
        {(dd) => { const d = { items: onlyBots ? dd.items.filter((r) => r.bots && (r.bots.ai + r.bots.search + r.bots.other) > 0) : dd.items }; return (
          <div>
            <div className="uf-tablebar"><ColumnMenu cols={COLS} state={cols} /></div>
            <div className="uf-scroll"><table className="widefat striped uf-table">
              <thead><tr>{COLS.filter((c) => cols.shown(c.id)).map((c) => <SortTh key={c.id} id={c.id} label={c.label} sort={sort} text={c.id === 'page'} onSort={setSort} />)}{window.userfloApp.canExclude ? <th scope="col"><span className="screen-reader-text">{__('Exclude')}</span></th> : null}</tr></thead>
              <tbody>{sortRows(d.items, sort, (r, by) => (by === 'page' ? r.url : by === 'views' ? r.views : by === 'visitors' ? r.visitors : r.avg_engaged)).map((r) => (
                <tr key={r.url} className={r.bots && r.bots.ai > 0 ? 'uf-row-ai' : r.bots && r.bots.search + r.bots.other > 0 ? 'uf-row-bot' : undefined}>
                  {cols.shown('page') ? <td><button className="button-link uf-strong" onClick={() => p.onOpen(r.url)}><code>{r.url}</code></button>{r.title ? <div><small>{r.title}</small></div> : null}
                    {r.bots && (r.bots.ai + r.bots.search + r.bots.other) > 0 ? <div className="uf-bot-badges">
                      {r.bots.ai ? <span className="uf-badge uf-badge-ai" title={__('Requests by AI crawlers in this period')}>{__('AI')} {num(r.bots.ai)}</span> : null}
                      {r.bots.search ? <span className="uf-badge uf-badge-search" title={__('Requests by search engines in this period')}>{__('Search')} {num(r.bots.search)}</span> : null}
                      {r.bots.other ? <span className="uf-badge uf-badge-bot" title={__('Requests by other bots in this period')}>{__('Bots')} {num(r.bots.other)}</span> : null}
                      {r.bots_only ? <small> {__('No human visits')}</small> : null}
                    </div> : null}</td> : null}
                  {cols.shown('health') ? <td><HealthDot health={r.health} url={r.url} /></td> : null}
                  {cols.shown('views') ? <td>{num(r.views)}</td> : null}
                  {cols.shown('visitors') ? <td>{num(r.visitors)}</td> : null}
                  {cols.shown('avg_engaged') ? <td>{duration(r.avg_engaged)}</td> : null}
                  {window.userfloApp.canExclude ? <td className="uf-excl-cell"><ExcludeButton subject={{ kind: 'page', url: r.url }} label={r.url} onDone={q.reload} /></td> : null}
                </tr>))}</tbody>
            </table></div>
          </div>
        ); }}
      </Async>
    </div>
  );
}

function PageDetail(p: { url: string; days: number; setDays: (n: number) => void; onBack: () => void }) {
  const [type, setType] = useState<'all' | 'anonymous' | 'logged_in'>('all');
  const [page, setPage] = useState(1);
  const q = useApi<PageVisits>('pages/visits', { url: p.url, days: p.days, type, page, per_page: PER });
  const presets = rangePresets([[1, __('24 hours')], [7, __('7 days')], [30, __('30 days')], [90, __('90 days')], [366, __('1 year')]]);
  return (
    <div>
      <p><button className="button" onClick={p.onBack}>{'« ' + __('All pages')}</button></p>
      <h1 className="uf-title"><code>{p.url}</code></h1>
      <PageAudit url={p.url} days={p.days} />
      {window.userfloApp.urls.heatmaps ? <p><a className="button" href={`${window.userfloApp.urls.heatmaps}&url=${encodeURIComponent(p.url)}&days=${p.days <= 7 ? 7 : p.days <= 30 ? 30 : p.days <= 90 ? 90 : 366}`}>{__('Open heatmap and scroll depth')}</a></p> : null}
      <div className="uf-filter">
        {presets.map(([d, l]) => <button key={d} className={'button' + (p.days === d ? ' button-primary' : '')} onClick={() => { p.setDays(d); setPage(1); }}>{l}</button>)}
        <label className="screen-reader-text" htmlFor="uf-pg-type">{__('Visitor type')}</label>
        <select id="uf-pg-type" value={type} onChange={(e) => { setType((e.target as HTMLSelectElement).value as typeof type); setPage(1); }}>
          <option value="all">{__('Everyone')}</option><option value="anonymous">{__('Anonymous')}</option><option value="logged_in">{__('Logged-in')}</option>
        </select>
      </div>
      <Async q={q} empty={(d) => d.items.length === 0} emptyText={__('No views of this page in the period.')}>
        {(d) => (
          <div>
            <p className="uf-sub">{num(d.total)} {__('views')}</p>
            <div className="uf-scroll"><table className="widefat striped uf-table">
              <thead><tr><th>{__('When')}</th><th>{__('Visitor')}</th><th>{__('Engaged')}</th><th>{__('Scrolled')}</th><th>{__('Came from')}</th><th>{__('Device')}</th></tr></thead>
              <tbody>{d.items.map((v) => (
                <tr key={v.id}>
                  <td>{dateTime(v.at)}</td>
                  <td><a href={visitorLink(v.visitor_id, v.session_id)} className="uf-strong">{v.label}</a> {v.logged_in ? <Badge kind="user">{__('Logged-in')}</Badge> : <Badge>{__('Anonymous')}</Badge>}{v.email ? <div><small>{v.email}</small></div> : null}</td>
                  <td>{duration(v.engaged)}</td><td>{v.scroll}%</td><td>{v.referrer || '—'}</td><td>{v.device}</td>
                </tr>))}</tbody>
            </table></div>
            <Pager page={page} total={d.total} perPage={PER} onPage={setPage} />
          </div>
        )}
      </Async>
    </div>
  );
}

export function Pages() {
  const [url, setUrl] = useState<string | null>(parsePageHash(window.location.hash));
  const [days, setDays] = useState(30);
  const [tab, setTab] = useState<'list' | 'health'>(window.location.hash === '#/health' ? 'health' : 'list');
  useEffect(() => {
    const on = () => setUrl(parsePageHash(window.location.hash));
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, []);
  const open = (u: string) => { window.location.hash = '#/p/' + encodeURIComponent(u); };
  if (url === null) {
    const tabs = modOn('audit') ? (
      <div className="uf-subtabs" role="group" aria-label={__('Pages view')}>
        <button type="button" className={'button' + (tab === 'list' ? ' button-primary' : '')} aria-pressed={tab === 'list'} onClick={() => { setTab('list'); window.location.hash = '#/'; }}>{__('Pages')}</button>
        <button type="button" className={'button' + (tab === 'health' ? ' button-primary' : '')} aria-pressed={tab === 'health'} onClick={() => { setTab('health'); window.location.hash = '#/health'; }}>{__('Page health')}</button>
      </div>
    ) : null;
    if (tab === 'health' && modOn('audit')) {
      return <div><h1 className="uf-title">{__('Pages')}</h1>{tabs}<PageHealth days={days} onOpen={open} /></div>;
    }
    return <div>{tabs}<PageList days={days} setDays={setDays} onOpen={open} /></div>;
  }
  return <PageDetail key={url} url={url} days={days} setDays={setDays} onBack={() => { window.location.hash = '#/'; }} />;
}
