import { h, __, sprintf, useState, useRef } from '../wp';
import type * as React from 'react';
import { arrange, moveId } from '../lists';

/** One card of a board: what it is called, whether it spans the full width, and what it shows (null = nothing to show right now). */
export interface PanelDef { id: string; title: string; wide?: boolean; node: React.ReactNode | null }

const PREFIX = 'userflo_board_v1_';
interface Layout { order: string[]; collapsed: string[] }

function load(key: string): Layout {
  try { const r = window.localStorage.getItem(PREFIX + key); if (r) { const v = JSON.parse(r) as Partial<Layout>; return { order: Array.isArray(v.order) ? v.order.filter((x) => typeof x === 'string') : [], collapsed: Array.isArray(v.collapsed) ? v.collapsed.filter((x) => typeof x === 'string') : [] }; } } catch (e) { /* storage unavailable */ }
  return { order: [], collapsed: [] };
}

export { arrange, moveId } from '../lists';

/**
 * A set of cards the viewer can collapse and rearrange. The choice is remembered in this browser only (a convenience: with nothing
 * stored, or storage blocked, the cards appear in their default order, all open). Dragging works with a pointer; the same grip
 * button moves a card with the arrow keys.
 */
export function Board(p: { storeKey: string; panels: PanelDef[]; grid?: boolean; gridClass?: string; bare?: boolean; wireframe?: boolean }) {
  const [lay, setLay] = useState<Layout>(() => load(p.storeKey));
  const [drag, setDrag] = useState<string | null>(null);
  const [over, setOver] = useState<string | null>(null);
  const [say, setSay] = useState('');
  const [wf, setWf] = useState(false);
  const armed = useRef<string | null>(null);
  const save = (l: Layout) => { setLay(l); try { window.localStorage.setItem(PREFIX + p.storeKey, JSON.stringify(l)); } catch (e) { /* ignore */ } };
  const shown = p.panels.filter((x) => x.node !== null);
  const ids = shown.map((x) => x.id);
  const order = arrange(ids, lay.order);
  const byId: Record<string, PanelDef> = {};
  shown.forEach((x) => { byId[x.id] = x; });
  const customised = lay.collapsed.length > 0 || order.some((x, i) => x !== ids[i]);

  const move = (id: string, to: number) => {
    const next = moveId(order, id, to);
    if (next === order) { return; }
    save({ ...lay, order: next });
    setSay(sprintf(__('%1$s moved to position %2$d of %3$d'), byId[id].title, next.indexOf(id) + 1, next.length));
  };
  const toggle = (id: string) => save({ ...lay, collapsed: lay.collapsed.indexOf(id) > -1 ? lay.collapsed.filter((x) => x !== id) : lay.collapsed.concat(id) });
  const allClosed = order.length > 0 && order.every((x) => lay.collapsed.indexOf(x) > -1);

  return (
    <div className="uf-board">
      <div className="uf-boardbar">
        {p.bare ? null : <button type="button" className="button" onClick={() => save({ order: lay.order, collapsed: allClosed ? [] : order.slice() })}>{allClosed ? __('Expand all') : __('Collapse all')}</button>}
        {p.wireframe ? <button type="button" className={'button' + (wf ? ' button-primary' : '')} aria-pressed={wf} onClick={() => setWf(!wf)}>{wf ? __('Done editing') : __('Edit layout')}</button> : null}
        {customised ? <button type="button" className="button" onClick={() => save({ order: [], collapsed: [] })}>{__('Reset layout')}</button> : null}
        <span className="uf-boardhint">{wf ? __('Drag a block (or focus its handle and use the arrow keys) to move it. Untick a block to hide it. Click Done editing to see the page again.') : p.bare ? __('Use Edit layout to rearrange or hide the blocks of this page. Your layout is remembered in this browser.') : __('Drag a card by its handle, or focus the handle and use the arrow keys, to rearrange. Your layout is remembered in this browser.')}</span>
      </div>
      <p className="screen-reader-text" role="status" aria-live="polite">{say}</p>
      {wf ? (
        <div className="uf-wf" role="list">
          {order.map((id, i) => {
            const d = byId[id], off = lay.collapsed.indexOf(id) > -1;
            return (
              <div key={id} role="listitem" className={'uf-wf-tile' + (d.wide ? ' is-wide' : '') + (off ? ' is-off' : '') + (drag === id ? ' is-dragging' : '') + (over === id && drag !== id ? ' is-over' : '')}
                draggable={drag === id}
                onDragStart={(e) => { if (armed.current !== id) { e.preventDefault(); return; } e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', id); } catch (x) { /* some browsers refuse */ } setDrag(id); }}
                onDragOver={(e) => { if (drag && drag !== id) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; setOver(id); } }}
                onDrop={(e) => { e.preventDefault(); if (drag && drag !== id) { move(drag, order.indexOf(id)); } setDrag(null); setOver(null); armed.current = null; }}
                onDragEnd={() => { setDrag(null); setOver(null); armed.current = null; }}>
                <div className="uf-wf-head">
                  <button type="button" className="uf-grip" aria-label={sprintf(__('Move “%s”. Use the arrow keys.'), d.title)} title={__('Drag to rearrange')}
                    onMouseDown={() => { armed.current = id; setDrag(id); }} onMouseUp={() => { armed.current = null; setDrag(null); }}
                    onKeyDown={(e) => {
                      const k = e.key;
                      if (k === 'ArrowUp' || k === 'ArrowLeft') { e.preventDefault(); move(id, i - 1); } else if (k === 'ArrowDown' || k === 'ArrowRight') { e.preventDefault(); move(id, i + 1); }
                      else if (k === 'Home') { e.preventDefault(); move(id, 0); } else if (k === 'End') { e.preventDefault(); move(id, order.length - 1); }
                    }}><span aria-hidden="true">⋮⋮</span></button>
                  <strong>{d.title}</strong>
                  <label className="uf-wf-show"><input type="checkbox" checked={!off} onChange={() => toggle(id)} /> <span>{__('Show')}</span></label>
                </div>
                <div className="uf-wf-sketch" aria-hidden="true"><i /><i /><i /></div>
              </div>
            );
          })}
        </div>
      ) : p.bare ? (
        <div className={p.gridClass || 'uf-boardgrid is-grid'}>
          {order.filter((id) => lay.collapsed.indexOf(id) < 0).map((id) => <div key={id} className={'uf-bare' + (byId[id].wide ? ' is-wide' : '')}>{byId[id].node}</div>)}
        </div>
      ) : null}
      {wf || p.bare ? null : <div className={p.grid ? 'uf-boardgrid is-grid' : 'uf-boardgrid'}>
        {order.map((id, i) => {
          const d = byId[id], closed = lay.collapsed.indexOf(id) > -1, bodyId = 'uf-bd-' + p.storeKey + '-' + id;
          return (
            <section key={id} className={'uf-card uf-panel' + (d.wide ? ' is-wide' : '') + (closed ? ' is-closed' : '') + (drag === id ? ' is-dragging' : '') + (over === id && drag !== id ? ' is-over' : '')}
              draggable={drag === id}
              onDragStart={(e) => { if (armed.current !== id) { e.preventDefault(); return; } e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', id); } catch (x) { /* some browsers refuse */ } setDrag(id); }}
              onDragOver={(e) => { if (drag && drag !== id) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; setOver(id); } }}
              onDrop={(e) => { e.preventDefault(); if (drag && drag !== id) { move(drag, order.indexOf(id)); } setDrag(null); setOver(null); armed.current = null; }}
              onDragEnd={() => { setDrag(null); setOver(null); armed.current = null; }}>
              <div className="uf-panel-head">
                <button type="button" className="uf-grip" aria-label={sprintf(__('Move “%s”. Use the arrow keys.'), d.title)} title={__('Drag to rearrange')}
                  onMouseDown={() => { armed.current = id; setDrag(id); }} onMouseUp={() => { armed.current = null; setDrag(null); }}
                  onKeyDown={(e) => {
                    const k = e.key;
                    if (k === 'ArrowUp' || k === 'ArrowLeft') { e.preventDefault(); move(id, i - 1); } else if (k === 'ArrowDown' || k === 'ArrowRight') { e.preventDefault(); move(id, i + 1); }
                    else if (k === 'Home') { e.preventDefault(); move(id, 0); } else if (k === 'End') { e.preventDefault(); move(id, order.length - 1); }
                  }}><span aria-hidden="true">⋮⋮</span></button>
                <h2>{d.title}</h2>
                <button type="button" className="uf-fold" aria-expanded={!closed} aria-controls={bodyId} onClick={() => toggle(id)}>
                  <span aria-hidden="true">{closed ? '▸' : '▾'}</span><span className="screen-reader-text">{closed ? sprintf(__('Expand “%s”'), d.title) : sprintf(__('Collapse “%s”'), d.title)}</span>
                </button>
              </div>
              <div id={bodyId} className="uf-panel-body" hidden={closed}>{d.node}</div>
            </section>
          );
        })}
      </div>}
    </div>
  );
}
