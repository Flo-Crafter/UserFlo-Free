import { h, __, useState, useEffect, rangePresets, can, modOn, sprintf, cfg } from '../wp';
import { useApi } from '../hooks';
import type { ElementDetailData, ElementRow, ElementsList } from '../types';
import { dayLabel, dateOnly, num } from '../format';
import { Async, Badge, Card, Empty, LimitNote, Pager, Stat } from '../components/ui';
import { SortTh } from '../components/table';
import type { Sort } from '../components/table';
import { ExcludeButton } from '../components/Exclude';
import type { ExcludeSubject } from '../components/Exclude';
import { slots } from '../slots';
import { send } from '../api';

const PER = 25;
const KINDS: [string, string][] = [['', __('All kinds')], ['button', __('Buttons')], ['link', __('Internal links')], ['external', __('Outbound links')], ['file', __('Files')], ['contact', __('E-mail and phone links')], ['form', __('Forms')], ['image', __('Images')], ['media', __('Video and audio')], ['anchor', __('Anchors')], ['control', __('Other controls')]];
const kindLabel = (k: string): string => (KINDS.find(([v]) => v === k) || ['', k])[1];
const AUDIENCES: [string, string][] = [['all', __('Everyone')], ['anonymous', __('Anonymous')], ['logged_in', __('Logged-in')]];

export function parseElementHash(hash: string): number | null {
  const m = /^#\/e\/(\d+)$/.exec(hash);
  return m ? Number(m[1]) : null;
}

/** What the exclude dialog needs to know about an element row. */
export function subjectOf(r: { kind: string; label: string; identifier: string; value: string }): ExcludeSubject {
  const etype = r.kind === 'external' ? 'outbound' : r.kind === 'file' ? 'download' : r.kind === 'form' ? 'form' : r.kind === 'media' ? 'media' : 'click';
  const byValue = ['link', 'external', 'file', 'contact', 'media'].indexOf(r.kind) > -1;
  return { kind: 'element', etype, name: r.label, target: r.identifier, value: byValue ? r.value : undefined };
}

/** "Show the technical line" is a per-viewer preference, remembered in this browser only. */
const TECH_KEY = 'userflo_el_tech';
function readTech(): boolean { try { return window.localStorage.getItem(TECH_KEY) !== '0'; } catch (e) { return true; } }
function saveTech(on: boolean) { try { window.localStorage.setItem(TECH_KEY, on ? '1' : '0'); } catch (e) { /* storage unavailable: the choice lasts for this page only */ } }

/** The name the administrator gave an element, or its own label. */
const shown = (r: { alias: string; label: string }): string => r.alias || r.label || __('(no label)');

/** Inline editor for an element's own name and note. Saving both empty removes them. */
function NoteEditor(p: { k: string; alias: string; note: string; label: string; onDone: () => void; onCancel: () => void }) {
  const [alias, setAlias] = useState(p.alias);
  const [note, setNote] = useState(p.note);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const save = async () => {
    setBusy(true); setErr('');
    try { await send('POST', 'elements/note', { key: p.k, alias, note }); p.onDone(); } catch (e) { setErr((e as Error).message); setBusy(false); }
  };
  return (
    <form className="uf-el-edit" onSubmit={(ev) => { ev.preventDefault(); save(); }}>
      <p><label>{__('Your name for it')}<br /><input type="text" className="regular-text" maxLength={80} value={alias} placeholder={p.label} onInput={(e) => setAlias((e.target as HTMLInputElement).value)} /></label></p>
      <p><label>{__('Note')}<br /><textarea className="large-text" rows={2} maxLength={300} value={note} placeholder={__('For example: the invoice download in the account area')} onInput={(e) => setNote((e.target as HTMLTextAreaElement).value)} /></label></p>
      <p className="description">{__('Only shown here, to administrators. It does not change what is tracked. Leave both empty to remove.')}</p>
      {err ? <p className="notice notice-error inline" role="alert">{err}</p> : null}
      <p><button type="submit" className="button button-primary" disabled={busy}>{__('Save')}</button> <button type="button" className="button" onClick={p.onCancel} disabled={busy}>{__('Cancel')}</button></p>
    </form>
  );
}

function Trend(p: { values: Record<string, number> }) {
  const days = Object.keys(p.values).sort();
  if (!days.length) { return <Empty>{__('No interactions in this period.')}</Empty>; }
  const max = Math.max(1, ...days.map((d) => p.values[d]));
  return (
    <div>
      <div className="uf-etrend" role="img" aria-label={sprintf(__('Interactions per day, from %1$s to %2$s, at most %3$s a day'), dayLabel(days[0]), dayLabel(days[days.length - 1]), num(max))}>
        {days.map((d) => <span key={d} title={`${dayLabel(d)}: ${p.values[d]}`} style={{ height: Math.max(3, (p.values[d] / max) * 100) + '%' }} />)}
      </div>
      <table className="screen-reader-text"><caption>{__('Interactions per day')}</caption><tbody>{days.map((d) => <tr key={d}><th scope="row">{dayLabel(d)}</th><td>{p.values[d]}</td></tr>)}</tbody></table>
    </div>
  );
}

function ElementDetail(p: { id: number; days: number; setDays: (n: number) => void; onBack: () => void }) {
  const [audience, setAudience] = useState('all');
  const [editing, setEditing] = useState(false);
  const [tech, setTech] = useState(readTech);
  const q = useApi<ElementDetailData>('elements/' + p.id, { days: p.days, audience });
  const presets = rangePresets([[7, __('7 days')], [30, __('30 days')], [90, __('90 days')]]);
  return (
    <div>
      <p><button type="button" className="button" onClick={p.onBack}>{'« ' + __('All elements')}</button></p>
      <Async q={q}>
        {(d) => {
          const Adv = slots().ElementAdvanced;
          const e = d.element;
          return (
            <div>
              <h1 className="uf-title">{shown(e)} <Badge>{kindLabel(e.kind)}</Badge><ExcludeButton subject={subjectOf({ kind: e.kind, label: e.label, identifier: e.identifier, value: e.value })} label={e.label || e.identifier} onDone={q.reload} /></h1>
              {e.alias && e.label ? <p className="uf-sub">{sprintf(__('Label on the page: %s'), e.label)}</p> : null}
              {e.note ? <p className="uf-sub"><em>{e.note}</em></p> : null}
              {cfg().canExclude ? (editing ? <NoteEditor k={e.key} alias={e.alias} note={e.note} label={e.label} onDone={() => { setEditing(false); q.reload(); }} onCancel={() => setEditing(false)} /> : <p><button type="button" className="button" onClick={() => setEditing(true)}>{e.alias || e.note ? __('Edit name and note') : __('Rename or add a note')}</button></p>) : null}
              {tech ? <p className="uf-sub"><code>{e.identifier}</code>{e.href ? <span> → <code>{e.href}</code></span> : null}</p> : null}
              <p className="uf-sub">{sprintf(__('On %1$d pages, %2$d times. First seen %3$s.'), e.pages, e.instances, dateOnly(e.first_seen + ' UTC'))}</p>
              <div className="uf-filter">
                {presets.map(([n, l]) => <button key={n} type="button" className={'button' + (p.days === n ? ' button-primary' : '')} onClick={() => p.setDays(n)}>{l}</button>)}
                <label className="screen-reader-text" htmlFor="uf-el-aud">{__('Audience')}</label>
                <select id="uf-el-aud" value={audience} onChange={(ev) => setAudience((ev.target as HTMLSelectElement).value)}>{AUDIENCES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
                <label><input type="checkbox" checked={tech} onChange={(ev) => { const on = (ev.target as HTMLInputElement).checked; setTech(on); saveTech(on); }} /> {__('Show technical details')}</label>
              </div>
              <div className="uf-stats">
                <Stat label={__('interactions')} value={num(d.interactions)} /><Stat label={__('visitors')} value={num(d.visitors)} />
                <Stat label={__('logged-in')} value={num(d.logged_in)} /><Stat label={__('anonymous')} value={num(d.anonymous)} />
              </div>
              <Card title={__('Over time')}><Trend values={d.trend} /></Card>
              <div className="uf-cols">
                <Card title={__('Where it is used')}>
                  {d.pages_used.length ? <ol className="uf-geo-list">{d.pages_used.map((x) => <li key={x.url}><a href={'#/p/' + encodeURIComponent(x.url)} onClick={(ev) => { ev.preventDefault(); window.location.href = (cfg().urls.pages || '') + '#/p/' + encodeURIComponent(x.url); }}><code>{x.url}</code></a> <strong>{num(x.interactions)}</strong></li>)}</ol> : <Empty>{__('No interactions in this period.')}</Empty>}
                </Card>
                <Card title={__('Where it is found')}>
                  {d.locations.length ? <ul className="uf-geo-list">{d.locations.map((x) => <li key={x.url}><code>{x.url}</code> {x.instances > 1 ? <small>×{x.instances}</small> : null}</li>)}</ul> : <Empty>{__('Not found on any scanned page.')}</Empty>}
                </Card>
              </div>
              {d.advanced && Adv ? <Adv data={d} days={p.days} audience={audience} onChange={q.reload} /> : <LimitNote feature="elements_advanced">{__('Pro adds the pages visitors came from and went to around this element, how often sessions that used it completed your goals, and its audit findings.')}</LimitNote>}
            </div>
          );
        }}
      </Async>
    </div>
  );
}

function ElementList(p: { days: number; setDays: (n: number) => void; onOpen: (id: number) => void }) {
  const [kind, setKind] = useState('');
  const [audience, setAudience] = useState('all');
  const [pageF, setPageF] = useState(() => { const v = new URLSearchParams(window.location.search).get('for') || ''; return v.charAt(0) === '/' ? v : ''; });
  const [input, setInput] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [tech, setTech] = useState(readTech);
  const [editKey, setEditKey] = useState('');
  const [sort, setSort] = useState<Sort>({ by: 'interactions', dir: 'desc' });
  useEffect(() => { const t = setTimeout(() => { setSearch(input.trim()); setPage(1); }, 300); return () => clearTimeout(t); }, [input]);
  const q = useApi<ElementsList>('elements', { days: p.days, audience, kind, page: pageF.charAt(0) === '/' ? pageF : '', search, sort: sort.by, dir: sort.dir, paged: page, per_page: PER });
  const presets = rangePresets([[7, __('7 days')], [30, __('30 days')], [90, __('90 days')]]);
  return (
    <div>
      <h1 className="uf-title">{__('Elements')}</h1>
      <p className="uf-sub">{__('The buttons, links, forms, images, files and videos FloLens found on your pages, with how visitors use them. “Not in inventory” means visitors used it but the scan did not find it, for example a button added by script.')}</p>
      <div className="uf-filter">
        {presets.map(([n, l]) => <button key={n} type="button" className={'button' + (p.days === n ? ' button-primary' : '')} onClick={() => { p.setDays(n); setPage(1); }}>{l}</button>)}
        <label className="screen-reader-text" htmlFor="uf-el-kind">{__('Kind of element')}</label>
        <select id="uf-el-kind" value={kind} onChange={(e) => { setKind((e.target as HTMLSelectElement).value); setPage(1); }}>{KINDS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
        <label className="screen-reader-text" htmlFor="uf-el-aud2">{__('Audience')}</label>
        <select id="uf-el-aud2" value={audience} onChange={(e) => { setAudience((e.target as HTMLSelectElement).value); setPage(1); }}>{AUDIENCES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
        <label className="screen-reader-text" htmlFor="uf-el-page">{__('Only elements on this page')}</label>
        <input id="uf-el-page" type="text" className="regular-text" placeholder={__('Only on page (for example /shop/)')} value={pageF} onInput={(e) => { setPageF((e.target as HTMLInputElement).value.trim()); setPage(1); }} />
        <label className="screen-reader-text" htmlFor="uf-el-search">{__('Search elements')}</label>
        <input id="uf-el-search" type="search" className="regular-text" placeholder={__('Search name, note, label, identifier or address…')} value={input} onInput={(e) => setInput((e.target as HTMLInputElement).value)} />
        <label><input type="checkbox" checked={tech} onChange={(e) => { const on = (e.target as HTMLInputElement).checked; setTech(on); saveTech(on); }} /> {__('Show technical details')}</label>
      </div>
      <Async q={q} empty={(d) => d.items.length === 0 && d.audit.pages === 0} emptyText={__('Nothing has been scanned yet. FloLens reads a few pages at a time in the background; the first elements appear within the hour. Interactions visitors have already made show up as soon as there are some.')}>
        {(d) => (
          <div>
            {d.items.length === 0 ? <Empty>{__('No elements match.')}</Empty> : (
              <div className="uf-scroll"><table className="widefat striped uf-table">
                <thead><tr>
                  <SortTh id="label" label={__('Element')} sort={sort} text onSort={(s) => { setSort(s); setPage(1); }} />
                  <SortTh id="pages" label={__('Pages')} sort={sort} onSort={(s) => { setSort(s); setPage(1); }} />
                  <SortTh id="interactions" label={__('Interactions')} sort={sort} onSort={(s) => { setSort(s); setPage(1); }} />
                  <SortTh id="visitors" label={__('Visitors')} sort={sort} onSort={(s) => { setSort(s); setPage(1); }} />
                  <th scope="col">{__('Logged-in / anonymous')}</th>
                  {cfg().canExclude ? <th scope="col"><span className="screen-reader-text">{__('Exclude')}</span></th> : null}
                </tr></thead>
                <tbody>{d.items.map((r: ElementRow, i) => (
                  <tr key={r.id || 'u' + i}>
                    <td>
                      {r.inventoried ? <button type="button" className="button-link uf-strong" onClick={() => p.onOpen(r.id)}>{shown(r)}</button> : <span className="uf-strong">{shown(r)}</span>}{' '}
                      <Badge>{kindLabel(r.kind)}</Badge>{r.inventoried ? null : <Badge kind="est">{__('Not in inventory')}</Badge>}
                      {r.alias && r.label ? <div><small>{sprintf(__('Label on the page: %s'), r.label)}</small></div> : null}
                      {r.note ? <div className="uf-el-note"><small><em>{r.note}</em></small></div> : null}
                      {tech ? <div><small><code>{r.identifier}</code>{r.value ? <span> → <code>{r.value}</code></span> : null}</small></div> : null}
                      {cfg().canExclude ? (editKey === r.key
                        ? <NoteEditor k={r.key} alias={r.alias} note={r.note} label={r.label} onDone={() => { setEditKey(''); q.reload(); }} onCancel={() => setEditKey('')} />
                        : <div><button type="button" className="button-link" onClick={() => setEditKey(r.key)}>{r.alias || r.note ? __('Edit name and note') : __('Rename or add a note')}<span className="screen-reader-text"> {shown(r)}</span></button></div>) : null}
                    </td>
                    <td>{r.inventoried ? (r.instances > r.pages ? `${num(r.pages)} (×${num(r.instances)})` : num(r.pages)) : '—'}</td>
                    <td>{num(r.interactions)}</td><td>{num(r.visitors)}</td><td>{num(r.logged_in)} / {num(r.anonymous)}</td>
                    {cfg().canExclude ? <td className="uf-excl-cell"><ExcludeButton subject={subjectOf(r)} label={r.label || r.identifier} onDone={q.reload} /></td> : null}
                  </tr>))}</tbody>
              </table></div>
            )}
            <Pager page={page} total={d.total} perPage={PER} onPage={setPage} />
            <p className="uf-sub">{__('Two elements that look the same to the tracker (same identifier and address) share their numbers. Excluding an element by its identifier covers every element with that identifier.')}</p>
            {can('elements_advanced') ? null : <LimitNote feature="elements_advanced">{__('Pro adds journeys around each element, goal correlation and audit findings per element.')}</LimitNote>}
          </div>
        )}
      </Async>
    </div>
  );
}

export function Elements() {
  const [id, setId] = useState<number | null>(parseElementHash(window.location.hash));
  const [days, setDays] = useState(30);
  useEffect(() => {
    const on = () => setId(parseElementHash(window.location.hash));
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, []);
  if (!modOn('elements')) { return <Empty>{__('Elements is turned off in Settings → Components.')}</Empty>; }
  if (id === null) { return <ElementList days={days} setDays={setDays} onOpen={(n) => { window.location.hash = '#/e/' + n; }} />; }
  return <ElementDetail key={id} id={id} days={days} setDays={setDays} onBack={() => { window.location.hash = '#/'; }} />;
}
