import { h, __, useState, useEffect, useRef, can, rangePresets } from '../wp';

/** A value in one cell of the exported file. */
export type Cell = string | number | boolean | null | undefined;
export interface ExportColumn { id: string; label: string }
/** The period the viewer picked: a number of days back from now, or exact dates (YYYY-MM-DD, both inclusive). */
export interface ExportRange { days: number; from?: string; to?: string }
/** One table that can be exported: its columns and how to fetch its rows for a period. */
export interface ExportSet {
  id: string;
  label: string;
  columns: ExportColumn[];
  load: (r: ExportRange) => Promise<Record<string, Cell>[]>;
  /** The data has no period (for example the audit inventory): the date range is not offered. */
  noRange?: boolean;
  /** Exact dates are accepted by the data behind this table (Pro; the same rule as the page's own date range). */
  custom?: boolean;
  /** Short note shown under the range (what the period means for this table). */
  note?: string;
}

/** Quotes a cell. A text cell starting with = + - @ (or a tab or return) gets a leading apostrophe so a spreadsheet never runs it as a formula. */
export function csvCell(v: Cell): string {
  if (v === null || v === undefined) { return ''; }
  let s = typeof v === 'boolean' ? (v ? '1' : '0') : String(v);
  if (typeof v === 'string' && /^[=+\-@\t\r]/.test(s) && !/^-?\d+(\.\d+)?$/.test(s)) { s = "'" + s; }
  return /[",\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}

/** The text of the file: one header row with the chosen columns, then one line per row. A byte-order mark lets Excel read UTF-8. */
export function toCsv(columns: ExportColumn[], rows: Record<string, Cell>[]): string {
  const out = [columns.map((c) => csvCell(c.label)).join(',')];
  rows.forEach((r) => out.push(columns.map((c) => csvCell(r[c.id])).join(',')));
  return '﻿' + out.join('\r\n') + '\r\n';
}

function download(name: string, text: string): void {
  const blob = new Blob([text], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = name; a.rel = 'noopener';
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}

const PREFIX = 'userflo_export_v1_';
const loadCols = (id: string): string[] | null => { try { const v = JSON.parse(window.localStorage.getItem(PREFIX + id) || 'null'); return Array.isArray(v) ? v.filter((x) => typeof x === 'string') : null; } catch (e) { return null; } };
const saveCols = (id: string, v: string[]): void => { try { window.localStorage.setItem(PREFIX + id, JSON.stringify(v)); } catch (e) { /* a convenience only */ } };

const today = (): string => new Date().toISOString().slice(0, 10);

/** The dialog: choose the table (when there are several), the period and the columns, then download. */
function ExportDialog(p: { sets: ExportSet[]; onClose: () => void }) {
  const [setId, setSetId] = useState(p.sets[0].id);
  const set = p.sets.find((s) => s.id === setId) || p.sets[0];
  const presets = rangePresets([[1, __('24 hours')], [7, __('7 days')], [30, __('30 days')], [90, __('90 days')], [366, __('1 year')]]);
  const [days, setDays] = useState(presets.some(([d]) => d === 30) ? 30 : presets[presets.length - 1][0]);
  const [mode, setMode] = useState<'days' | 'custom'>('days');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [picked, setPicked] = useState<string[]>(() => loadCols(set.id) || set.columns.map((c) => c.id));
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const box = useRef<HTMLDivElement | null>(null);
  const customOk = !!set.custom && can('range_extended');

  useEffect(() => {
    const prev = document.activeElement as HTMLElement | null;
    const key = (e: KeyboardEvent) => { if (e.key === 'Escape') { p.onClose(); } };
    document.addEventListener('keydown', key);
    document.body.classList.add('uf-modal-open');
    if (box.current) { box.current.focus(); }
    return () => { document.removeEventListener('keydown', key); document.body.classList.remove('uf-modal-open'); if (prev) { prev.focus(); } };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => { setPicked(loadCols(set.id) || set.columns.map((c) => c.id)); setErr(''); if (!set.custom) { setMode('days'); } }, [setId]);

  const toggle = (id: string) => setPicked(picked.indexOf(id) < 0 ? picked.concat(id) : picked.filter((x) => x !== id));
  const cols = set.columns.filter((c) => picked.indexOf(c.id) > -1);
  const datesOk = mode !== 'custom' || (from !== '' && to !== '' && from <= to);
  const go = () => {
    if (!cols.length || !datesOk) { return; }
    setBusy(true); setErr('');
    saveCols(set.id, picked);
    const range: ExportRange = mode === 'custom' && customOk ? { days, from, to } : { days };
    set.load(range).then((rows) => {
      setBusy(false);
      if (!rows.length) { setErr(__('There is nothing to export for this period.')); return; }
      download('userflo-' + set.id + '-' + today() + '.csv', toCsv(cols, rows));
      p.onClose();
    }).catch((e: { message?: string }) => { setBusy(false); setErr(e && e.message ? e.message : __('The export failed.')); });
  };

  return (
    <div className="uf-modal-bg" onClick={(e) => { if (e.target === e.currentTarget) { p.onClose(); } }}>
      <div className="uf-modal uf-export" role="dialog" aria-modal="true" aria-labelledby="uf-export-title" tabIndex={-1} ref={box}>
        <div className="uf-modal-top"><h2 id="uf-export-title">{__('Export as CSV')}</h2><button type="button" className="button" onClick={p.onClose}>{__('Close')}</button></div>
        {p.sets.length > 1 ? (
          <p><label>{__('What to export')} <select value={setId} onChange={(e) => setSetId((e.target as HTMLSelectElement).value)}>{p.sets.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}</select></label></p>
        ) : null}
        {set.noRange ? <p className="uf-sub">{__('This table is a snapshot of the current scan, so there is no date range.')}</p> : (
          <fieldset className="uf-export-range">
            <legend>{__('Date range')}</legend>
            <div role="group" aria-label={__('Period')}>
              {presets.map(([d, l]) => <button key={d} type="button" className={'button' + (mode === 'days' && days === d ? ' button-primary' : '')} aria-pressed={mode === 'days' && days === d} onClick={() => { setMode('days'); setDays(d); }}>{l}</button>)}
              {customOk ? <button type="button" className={'button' + (mode === 'custom' ? ' button-primary' : '')} aria-pressed={mode === 'custom'} onClick={() => setMode('custom')}>{__('Custom')}</button> : null}
            </div>
            {mode === 'custom' ? <p><label>{__('From')} <input type="date" value={from} max={to || undefined} onChange={(e) => setFrom((e.target as HTMLInputElement).value)} /></label> <label>{__('To')} <input type="date" value={to} min={from || undefined} onChange={(e) => setTo((e.target as HTMLInputElement).value)} /></label></p> : null}
            {set.note ? <p className="uf-sub">{set.note}</p> : null}
          </fieldset>
        )}
        <fieldset className="uf-export-cols">
          <legend>{__('Columns')}</legend>
          <p><button type="button" className="button-link" onClick={() => setPicked(set.columns.map((c) => c.id))}>{__('Select all')}</button> · <button type="button" className="button-link" onClick={() => setPicked([])}>{__('Select none')}</button></p>
          <div className="uf-export-grid">{set.columns.map((c) => <label key={c.id}><input type="checkbox" checked={picked.indexOf(c.id) > -1} onChange={() => toggle(c.id)} /> {c.label}</label>)}</div>
        </fieldset>
        {err ? <p className="notice notice-error inline" role="alert"><span>{err}</span></p> : null}
        <p className="uf-export-go"><button type="button" className="button button-primary" disabled={busy || !cols.length || !datesOk} onClick={go}>{busy ? __('Preparing…') : __('Download CSV')}</button></p>
      </div>
    </div>
  );
}

/** The "Export CSV" button for a screen; the dialog opens on click. */
export function ExportButton(p: { sets: ExportSet[] }) {
  const [open, setOpen] = useState(false);
  if (!p.sets.length) { return null; }
  return (
    <span className="uf-export-btn">
      <button type="button" className="button" onClick={() => setOpen(true)}>{__('Export CSV')}</button>
      {open ? <ExportDialog sets={p.sets} onClose={() => setOpen(false)} /> : null}
    </span>
  );
}
