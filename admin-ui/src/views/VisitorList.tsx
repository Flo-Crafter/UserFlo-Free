import { h, __, useState, useEffect } from '../wp';
import { slots } from '../slots';
import { useApi } from '../hooks';
import type { Paged, Visitor } from '../types';
import { dateTime, duration } from '../format';
import type * as React from 'react';
import { Async, Badge, Pager } from '../components/ui';
import { ColumnMenu, SortTh, useColumns } from '../components/table';
import type { Col } from '../components/table';
import { Flag } from '../components/Flag';

interface Filters {
  type: 'all' | 'anonymous' | 'logged_in';
  orderby: string; order: 'asc' | 'desc';
}
const EMPTY: Filters = { type: 'all', orderby: 'last_seen', order: 'desc' };

const COLS: Col[] = [
  { id: 'visitor', label: __('Visitor'), fixed: true }, { id: 'type', label: __('Type') }, { id: 'first_seen', label: __('First seen') }, { id: 'last_seen', label: __('Last seen') },
  { id: 'sessions', label: __('Sessions') }, { id: 'pageviews', label: __('Page views') }, { id: 'engaged', label: __('Engaged') }, { id: 'location', label: __('Location') }, { id: 'device', label: __('Device') },
];

/** The event filter is one select whose value is "type" or "type|name". */
function eventParams(v: string): { event_type?: string; event_name?: string } {
  if (!v) { return {}; }
  const i = v.indexOf('|');
  return i < 0 ? { event_type: v } : { event_type: v.slice(0, i), event_name: v.slice(i + 1) };
}

/** The content of one cell; the table calls it for each shown column in the user's column order. */
function cell(id: string, v: Visitor, onOpen: (id: number) => void): React.ReactNode {
  switch (id) {
    case 'visitor': return <span><button className="button-link uf-strong" onClick={() => onOpen(v.id)}>{v.label}</button>{v.email ? <div><small>{v.email}</small></div> : null}</span>;
    case 'type': return <span>{v.logged_in ? <Badge kind="user">{__('Logged-in')}</Badge> : <Badge>{__('Anonymous')}</Badge>} {v.returning ? <Badge kind="ret">{__('Returning')}</Badge> : null}</span>;
    case 'first_seen': return dateTime(v.first_seen);
    case 'last_seen': return dateTime(v.last_seen);
    case 'sessions': return v.sessions;
    case 'pageviews': return v.pageviews;
    case 'engaged': return duration(v.engaged);
    case 'location': return v.location ? <span className="uf-loc"><Flag code={v.country} name={v.country_name} /> <span>{v.location}</span></span> : '—';
    case 'device': return [v.device, v.browser, v.os].filter(Boolean).join(' · ');
    default: return null;
  }
}

export function VisitorList(p: { onOpen: (id: number) => void }) {
  const [f, setF] = useState<Filters>(EMPTY);
  // Extra filters owned by Pro's VisitorFilters slot (from/to, page, event, referrer, campaign, device, country, visits, min. sessions).
  const [x, setX] = useState<Record<string, string>>({});
  const Filters = slots().VisitorFilters;
  const [input, setInput] = useState('');
  const [search, setSearch] = useState('');
  const [open, setOpen] = useState(false);
  const [page, setPage] = useState(1);
  const PER = 25;
  const cols = useColumns('visitors', COLS, { remote: true });
  const shownCols = cols.ordered.filter((c) => cols.shown(c.id));
  const sort = { by: f.orderby, dir: f.order };

  const set = (patch: Partial<Filters>) => { setF((o) => ({ ...o, ...patch })); setPage(1); };
  useEffect(() => { const t = setTimeout(() => { setSearch(input.trim()); setPage(1); }, 300); return () => clearTimeout(t); }, [input]);

  const range = x.from ? { from: x.from, to: x.to || x.from } : {};
  const q = useApi<Paged<Visitor>>('visitors', { type: f.type, visits: x.visits || 'all', search, ...range, url: x.url || '', ...eventParams(x.event || ''), referrer: x.referrer || '', campaign: x.campaign || '', device: x.device || '', country: x.country || '', min_sessions: x.min_sessions || '', orderby: f.orderby, order: f.order, page, per_page: PER });
  const chips: [string, string, () => void][] = [];
  if (f.type !== 'all') { chips.push([__('Type'), f.type === 'logged_in' ? __('Logged-in') : __('Anonymous'), () => set({ type: 'all' })]); }
  const clear = () => { setF(EMPTY); setX({}); setInput(''); setPage(1); };
  const fp = { open, setOpen, values: x, onChange: (patch: Record<string, string>) => { setX((o) => ({ ...o, ...patch })); setPage(1); }, sort: { by: f.orderby, dir: f.order }, onSort: (by: string, dir: 'asc' | 'desc') => set({ orderby: by, order: dir }) };
  const nChips = (f.type !== 'all' ? 1 : 0);

  return (
    <div>
      <h1 className="uf-title">{__('Visitors')}</h1>
      <p className="uf-sub">{__('Find visitors by who they are, where they came from, what they viewed and what they did. All filters combine (a visitor must match every one).')}</p>
      <div className="uf-filter" role="search">
        <label className="screen-reader-text" htmlFor="uf-type">{__('Visitor type')}</label>
        <select id="uf-type" value={f.type} onChange={(e) => set({ type: (e.target as HTMLSelectElement).value as Filters['type'] })}>
          <option value="all">{__('All visitors')}</option><option value="anonymous">{__('Anonymous')}</option><option value="logged_in">{__('Logged-in')}</option>
        </select>
        <label className="screen-reader-text" htmlFor="uf-search">{__('Search visitors')}</label>
        <input id="uf-search" type="search" className="regular-text" placeholder={__('Search visitor ID, username, email…')} value={input} onInput={(e) => setInput((e.target as HTMLInputElement).value)} />
        {Filters ? <Filters part="button" {...fp} /> : null}
      </div>

      {Filters ? <Filters part="panel" {...fp} /> : null}

      {nChips || Object.values(x).some((z) => z && z !== 'all') || search ? (
        <p className="uf-vf-chips" aria-label={__('Active filters')}>
          {f.type !== 'all' ? <button type="button" className="uf-chip" onClick={() => set({ type: 'all' })} aria-label={`${__('Remove filter')}: ${__('Type')} ${f.type === 'logged_in' ? __('Logged-in') : __('Anonymous')}`}>{__('Type')}: {f.type === 'logged_in' ? __('Logged-in') : __('Anonymous')} <span aria-hidden="true">×</span></button> : null}
          {Filters ? <Filters part="chips" {...fp} /> : null}
          <button type="button" className="button-link" onClick={clear}>{__('Clear all')}</button>
        </p>
      ) : null}

      <Async q={q} empty={(d) => d.items.length === 0} emptyText={__('No visitors match.')}>
        {(d) => (
          <div>
            <p className="uf-sub" role="status">{d.total === 1 ? __('1 visitor') : `${d.total.toLocaleString()} ${__('visitors')}`}</p>
            <div className="uf-tablebar"><ColumnMenu cols={COLS} state={cols} /></div>
            <div className="uf-scroll"><table className="widefat striped uf-table">
              <thead><tr>
                {shownCols.map((c) => <SortTh key={c.id} id={c.id} label={c.label} sort={sort} text={c.id === 'visitor' || c.id === 'type' || c.id === 'location' || c.id === 'device'} onSort={(x) => set({ orderby: x.by, order: x.dir })} />)}
              </tr></thead>
              <tbody>{d.items.map((v) => (
                <tr key={v.id}>
                  {shownCols.map((c) => <td key={c.id}>{cell(c.id, v, p.onOpen)}</td>)}
                </tr>))}</tbody>
            </table></div>
            <Pager page={page} total={d.total} perPage={PER} onPage={setPage} />
          </div>
        )}
      </Async>
    </div>
  );
}
