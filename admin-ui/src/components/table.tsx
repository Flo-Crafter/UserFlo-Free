import { h, __, useState, useEffect, useRef, useCallback } from '../wp';
import type * as React from 'react';
import { firstDir } from '../lists';
import { del, send } from '../api';
import { isDefaultOrder, mergeOrder, moveItem } from '../columnOrder';
import type { Sort } from '../lists';

/** A table column the user may hide. `fixed` columns (the one that names the row) cannot be hidden. */
export interface Col { id: string; label: string; fixed?: boolean }

const PREFIX = 'userflo_cols_v1_';

function loadHidden(key: string): string[] {
  try { const r = window.localStorage.getItem(PREFIX + key); if (r) { const v = JSON.parse(r); if (Array.isArray(v)) { return v.filter((x) => typeof x === 'string'); } } } catch (e) { /* storage unavailable */ }
  return [];
}
function saveHidden(key: string, v: string[]) { try { if (v.length) { window.localStorage.setItem(PREFIX + key, JSON.stringify(v)); } else { window.localStorage.removeItem(PREFIX + key); } } catch (e) { /* ignore */ } }

interface Remote { order: string[]; hidden: string[] }
/** The layout the server holds for this user and table, if the person ever changed it. */
function serverLayout(key: string): Remote | null {
  const all = (window.userfloApp && window.userfloApp.tablePrefs) || {};
  const r = all[key];
  return r && Array.isArray(r.order) && Array.isArray(r.hidden) ? { order: r.order, hidden: r.hidden } : null;
}
function pushLayout(key: string, v: Remote | null) {
  const app = window.userfloApp; if (app) { app.tablePrefs = app.tablePrefs || {}; if (v) { app.tablePrefs[key] = v; } else { delete app.tablePrefs[key]; } }
  const done = () => { /* the layout also lives in this page, so a failed save only means it is not remembered next time */ };
  if (v) { send('POST', 'table-prefs/' + key, { order: v.order, hidden: v.hidden }).then(done, done); } else { del('table-prefs/' + key).then(done, done); }
}

/**
 * Which columns are shown and in what order. With `remote` the layout belongs to the signed-in WordPress user (kept on the server, so it
 * follows them between browsers); a layout saved before that in this browser (hidden columns) is picked up until the person changes
 * something. Without `remote` only the hidden columns are remembered in this browser, as before. The table renders fully without any of it.
 */
export function useColumns(key: string, cols: Col[], opts?: { remote?: boolean }) {
  const remote = !!(opts && opts.remote);
  const ids = cols.map((c) => c.id);
  const [layout, setLayout] = useState<Remote>(() => {
    const srv = remote ? serverLayout(key) : null;
    return srv ? srv : { order: [], hidden: loadHidden(key) };
  });
  const order = mergeOrder(ids, layout.order);
  const save = (next: Remote) => {
    setLayout(next);
    if (remote) { pushLayout(key, next.order.length || next.hidden.length ? next : null); saveHidden(key, []); } else { saveHidden(key, next.hidden); }
  };
  const ordered = order.map((id) => cols.find((c) => c.id === id) as Col);
  const shown = (id: string): boolean => { const c = cols.find((x) => x.id === id); return !!c && (!!c.fixed || layout.hidden.indexOf(id) < 0); };
  const toggle = (id: string) => { save({ order: layout.order, hidden: layout.hidden.indexOf(id) < 0 ? layout.hidden.concat(id) : layout.hidden.filter((x) => x !== id) }); };
  const move = (id: string, to: number) => { const o = moveItem(order, order.indexOf(id), to); save({ order: isDefaultOrder(ids, o) ? [] : o, hidden: layout.hidden }); };
  const showAll = () => save({ order: layout.order, hidden: [] });
  /** Back to the default columns in the default order. */
  const reset = () => save({ order: [], hidden: [] });
  const hiddenCount = cols.filter((c) => !c.fixed && layout.hidden.indexOf(c.id) > -1).length;
  const customOrder = !isDefaultOrder(ids, order);
  return { shown, toggle, reset, showAll, move, order, ordered, hiddenCount, customOrder, reorder: remote };
}

/** "Columns" button with a list of checkboxes; with reordering on, each row also has a drag handle and up/down buttons. Closes on Escape or a click outside. */
export function ColumnMenu(p: { cols: Col[]; state: ReturnType<typeof useColumns> }) {
  const ref = useRef<HTMLDetailsElement | null>(null);
  const listRef = useRef<HTMLDivElement | null>(null);
  const [drag, setDrag] = useState<string | null>(null);
  const [said, setSaid] = useState('');
  useEffect(() => {
    const close = (e: Event) => { const d = ref.current; if (d && d.open && (e.type === 'keydown' ? (e as KeyboardEvent).key === 'Escape' : !d.contains(e.target as Node))) { d.open = false; if (e.type === 'keydown') { const s = d.querySelector('summary') as HTMLElement | null; if (s) { s.focus(); } } } };
    document.addEventListener('click', close); document.addEventListener('keydown', close);
    return () => { document.removeEventListener('click', close); document.removeEventListener('keydown', close); };
  }, []);
  const st = p.state;
  const label = (id: string) => (p.cols.find((c) => c.id === id) || { label: id }).label;
  const step = (id: string, by: number) => {
    const at = st.order.indexOf(id); const to = at + by;
    if (to < 0 || to >= st.order.length) { return; }
    st.move(id, to);
    setSaid(label(id) + ': ' + __('position') + ' ' + (to + 1) + ' / ' + st.order.length);
    // Keep the focus on the same button after the list re-renders in its new order.
    setTimeout(() => { const b = listRef.current && listRef.current.querySelector('[data-id="' + id + '"] [data-dir="' + (by < 0 ? 'up' : 'down') + '"]:not([disabled])') as HTMLElement | null; const alt = listRef.current && listRef.current.querySelector('[data-id="' + id + '"] [data-dir]:not([disabled])') as HTMLElement | null; (b || alt || document.body).focus(); }, 0);
  };
  // Dragging uses pointer events, so the same code serves a mouse, a finger and a pen. The handle has touch-action:none so a finger drags the row instead of scrolling the page.
  const onDown = (id: string, e: React.PointerEvent) => { if (e.button > 0) { return; } (e.target as HTMLElement).setPointerCapture(e.pointerId); setDrag(id); };
  const onMove = (id: string, e: React.PointerEvent) => {
    if (drag !== id || !listRef.current) { return; }
    const rows = Array.prototype.slice.call(listRef.current.querySelectorAll('[data-id]')) as HTMLElement[];
    let to = rows.length - 1;
    for (let i = 0; i < rows.length; i++) { const r = rows[i].getBoundingClientRect(); if (e.clientY < r.top + r.height / 2) { to = i; break; } }
    if (to !== st.order.indexOf(id)) { st.move(id, to); }
  };
  const onUp = (id: string, e: React.PointerEvent) => { if (drag === id) { try { (e.target as HTMLElement).releasePointerCapture(e.pointerId); } catch (x) { /* already released */ } setDrag(null); } };
  const changed = st.hiddenCount > 0 || st.customOrder;
  return (
    <details className="uf-colmenu" ref={ref}>
      <summary className="button">{__('Columns')}{st.hiddenCount ? ` (${st.hiddenCount} ${__('hidden')})` : ''}</summary>
      <div className="uf-colmenu-box" role="group" aria-label={st.reorder ? __('Show, hide or reorder columns') : __('Show or hide columns')}>
        {st.reorder ? <small className="uf-colmenu-hint">{__('Drag a row by its handle, or use the arrows, to change the column order.')}</small> : null}
        <div ref={listRef} className="uf-colmenu-list">
          {(st.reorder ? st.ordered : p.cols).map((c, i, all) => (
            <div key={c.id} data-id={c.id} className={'uf-colmenu-row' + (drag === c.id ? ' is-dragging' : '')}>
              {st.reorder ? <span className="uf-colmenu-grip" role="presentation" aria-hidden="true" title={__('Drag to reorder')} onPointerDown={(e) => onDown(c.id, e)} onPointerMove={(e) => onMove(c.id, e)} onPointerUp={(e) => onUp(c.id, e)} onPointerCancel={(e) => onUp(c.id, e)}>⠿</span> : null}
              <label><input type="checkbox" checked={st.shown(c.id)} disabled={!!c.fixed} onChange={() => st.toggle(c.id)} /> {c.label}</label>
              {st.reorder ? (
                <span className="uf-colmenu-arrows">
                  <button type="button" data-dir="up" className="button-link" disabled={i === 0} aria-label={__('Move up') + ': ' + c.label} onClick={() => step(c.id, -1)}>▲</button>
                  <button type="button" data-dir="down" className="button-link" disabled={i === all.length - 1} aria-label={__('Move down') + ': ' + c.label} onClick={() => step(c.id, 1)}>▼</button>
                </span>
              ) : null}
            </div>
          ))}
        </div>
        <span className="screen-reader-text" role="status" aria-live="polite">{said}</span>
        {st.reorder ? (changed ? <button type="button" className="button-link" onClick={st.reset}>{__('Reset to default columns')}</button> : null) : (st.hiddenCount ? <button type="button" className="button-link" onClick={st.showAll}>{__('Show all columns')}</button> : null)}
      </div>
    </details>
  );
}

export type { Sort } from '../lists';
export { sortRows } from '../lists';

/** A column header you can click to sort by it; clicking again reverses the order. */
export function SortTh(p: { id: string; label: string; sort: Sort; onSort: (s: Sort) => void; text?: boolean; children?: React.ReactNode }) {
  const on = p.sort.by === p.id;
  const click = useCallback(() => p.onSort(on ? { by: p.id, dir: p.sort.dir === 'asc' ? 'desc' : 'asc' } : { by: p.id, dir: firstDir(!!p.text) }), [on, p.sort.dir, p.id, p.text, p.onSort]);
  return (
    <th scope="col" aria-sort={on ? (p.sort.dir === 'asc' ? 'ascending' : 'descending') : undefined}>
      <button type="button" className="uf-sortbtn" onClick={click}>
        <span>{p.label}</span>
        <span className="uf-sortico" aria-hidden="true">{on ? (p.sort.dir === 'asc' ? '▲' : '▼') : '↕'}</span>
        <span className="screen-reader-text">{on ? (p.sort.dir === 'asc' ? __('sorted ascending; activate to sort descending') : __('sorted descending; activate to sort ascending')) : __('activate to sort')}</span>
      </button>
    </th>
  );
}
