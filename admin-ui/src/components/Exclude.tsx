import { h, __, useState, useEffect, useRef, sprintf, cfg } from '../wp';
import { send } from '../api';
import type { ExclusionImpact, ExclusionRule } from '../types';
import { num } from '../format';

/** What the person clicked: a page, or an interaction the tracker recorded (any of target/name/value may be empty). */
export type ExcludeSubject =
  | { kind: 'page'; url: string }
  | { kind: 'element'; etype: string; name?: string; target?: string; value?: string; component?: string; page?: string }
  | { kind: 'finding'; code: string; title: string; url: string; element?: { identifier: string; label: string } | null };

type Mode = 'stop' | 'stats' | 'audit';
interface Scope { id: string; label: string; hint?: string; body: Record<string, string> }

export const MODE_LABEL: Record<Mode, string> = { stop: __('Stop tracking'), stats: __('Measure, but leave out of statistics'), audit: __('Hide audit findings') };

const EFFECT: Record<Mode, string> = {
  stop: __('From now on nothing is recorded for this. Data that was already collected stays where it is and still counts in statistics, unless you also add a “leave out of statistics” rule.'),
  stats: __('It is still collected and stored (subject to your privacy settings and retention), but it is left out of totals, rankings, rates, trends, reports, exports and journeys. Pause or delete the rule at any time to count it again.'),
  audit: __('Matching findings stop counting as issues and are listed separately as excluded. The page or element stays in the inventory and in your analytics.'),
};

const stripQuery = (u: string): string => { const q = u.indexOf('?'); return q < 0 ? u : u.slice(0, q); };

/** The choices of scope for a subject, most specific first. */
export function scopesFor(s: ExcludeSubject): Scope[] {
  if (s.kind === 'finding') {
    const path = stripQuery(s.url) || '/';
    const out: Scope[] = [];
    if (s.element) {
      out.push({ id: 'f_el_page', label: __('This element, on this page'), hint: s.element.identifier, body: { target: 'finding', finding_type: s.code, pattern: s.element.identifier, match_type: 'exact', page_scope: path } });
      out.push({ id: 'f_el', label: __('This element, on every page'), hint: s.element.identifier, body: { target: 'finding', finding_type: s.code, pattern: s.element.identifier, match_type: 'exact' } });
    }
    out.push({ id: 'f_page', label: __('This kind of finding, on this page'), hint: s.title + ' · ' + path, body: { target: 'finding', finding_type: s.code, page_scope: path } });
    out.push({ id: 'f_site', label: __('This kind of finding, across the whole website'), hint: s.title, body: { target: 'finding', finding_type: s.code } });
    return out;
  }
  if (s.kind === 'page') {
    const path = stripQuery(s.url) || '/';
    return [
      { id: 'page', label: __('This page only'), hint: path, body: { target: 'page', field: 'url', match_type: 'exact', pattern: path } },
      ...(path !== '/' ? [{ id: 'tree', label: __('This page and everything below it'), hint: path.replace(/\/?$/, '/') + '…', body: { target: 'page', field: 'url', match_type: 'prefix', pattern: path.replace(/\/?$/, '/') } }] : []),
    ];
  }
  const out: Scope[] = [];
  if (s.target) {
    if (s.page) { out.push({ id: 'el_page', label: __('This element, on this page only'), hint: s.target, body: { target: 'element', field: 'target', match_type: 'exact', pattern: s.target, page_scope: stripQuery(s.page) } }); }
    out.push({ id: 'el', label: __('This element, on every page'), hint: s.target, body: { target: 'element', field: 'target', match_type: 'exact', pattern: s.target } });
  }
  if (s.value) {
    out.push({ id: 'val', label: s.etype === 'download' ? __('Every download of this file') : __('Every link to this address'), hint: s.value, body: { target: 'element', field: 'value', match_type: 'exact', pattern: s.value, event_type: s.etype } });
  }
  if (s.name) {
    out.push({ id: 'name', label: sprintf(__('Everything labelled “%s”'), s.name), hint: __('Any element with this label, on every page'), body: { target: 'element', field: 'name', match_type: 'exact', pattern: s.name, event_type: s.etype } });
  }
  return out;
}

export function describeRule(r: ExclusionRule): string {
  const how = r.match_type === 'exact' ? __('is exactly') : r.match_type === 'prefix' ? __('starts with') : __('contains');
  const scope = r.page_scope ? ' ' + sprintf(__('(only on pages under %s)'), r.page_scope) : '';
  if (r.target === 'page') { return sprintf(__('Page address %1$s %2$s'), how, r.pattern); }
  if (r.target === 'finding') {
    const t = r.finding_type ? sprintf(__('Finding “%s”'), r.finding_type) : __('Any finding');
    return t + (r.pattern ? ' ' + sprintf(__('for the element %s'), r.pattern) : '') + scope;
  }
  const field: Record<string, string> = { target: __('Element identifier'), name: __('Label'), value: __('Address or file'), component: __('Section'), css: __('CSS selector') };
  const kind = r.event_type ? ' [' + r.event_type + ']' : '';
  return (field[r.field] || r.field) + ' ' + (r.field === 'css' ? '' : how + ' ') + r.pattern + kind + scope;
}

/** A small button shown at the end of a row; revealed on hover and on keyboard focus. */
export function ExcludeButton(p: { subject: ExcludeSubject; label: string; onDone?: () => void }) {
  const [open, setOpen] = useState(false);
  if (!cfg().canExclude) { return null; }
  return (
    <span className="uf-exclude">
      <button type="button" className="uf-exclude-btn" aria-haspopup="dialog" aria-label={sprintf(__('Exclude %s…'), p.label)} title={__('Exclude…')} onClick={() => setOpen(true)}>
        <svg width="14" height="14" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="7.5" fill="none" stroke="currentColor" strokeWidth="1.8" /><path d="M5 15L15 5" stroke="currentColor" strokeWidth="1.8" /></svg>
      </button>
      {open ? <ExcludeDialog subject={p.subject} label={p.label} onClose={() => setOpen(false)} onDone={() => { if (p.onDone) { p.onDone(); } }} /> : null}
    </span>
  );
}

export function ExcludeDialog(p: { subject: ExcludeSubject; label: string; onClose: () => void; onDone: () => void }) {
  const scopes = scopesFor(p.subject);
  const audit = !!cfg().exclusionsAudit;
  const [scope, setScope] = useState(scopes.length ? scopes[0].id : '');
  const isFinding = p.subject.kind === 'finding';
  const [mode, setMode] = useState<Mode>(isFinding ? 'audit' : 'stats');
  const [note, setNote] = useState('');
  const [impact, setImpact] = useState<ExclusionImpact | null>(null);
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState(false);
  const savedRef = useRef(false);
  const box = useRef<HTMLDivElement | null>(null);
  // The list behind the dialog is refreshed only when the dialog closes: the row being excluded would disappear, and take the dialog with it.
  const close = () => { p.onClose(); if (savedRef.current) { p.onDone(); } };
  const chosen = scopes.find((s) => s.id === scope);

  useEffect(() => {
    const prev = document.activeElement as HTMLElement | null;
    if (box.current) { box.current.focus(); }
    const key = (e: KeyboardEvent) => { if (e.key === 'Escape') { close(); } };
    document.addEventListener('keydown', key);
    document.body.classList.add('uf-modal-open');
    return () => { document.removeEventListener('keydown', key); document.body.classList.remove('uf-modal-open'); if (prev) { prev.focus(); } };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const body = (): Record<string, string> => ({ mode, label: p.label, note, ...(chosen ? chosen.body : {}) });
  useEffect(() => {
    if (!chosen) { return; }
    let live = true;
    setImpact(null);
    send<ExclusionImpact>('POST', 'exclusions/preview', body()).then((r) => { if (live) { setImpact(r); } }).catch((e) => { if (live) { setErr((e as Error).message); } });
    return () => { live = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [scope, mode]);

  const confirm = async () => {
    setErr(''); setBusy(true);
    try { await send('POST', 'exclusions', body()); savedRef.current = true; setSaved(true); } catch (e) { setErr((e as Error).message); }
    setBusy(false);
  };

  const effect = impact && impact.known ? (
    <p className="uf-excl-impact">
      {impact.findings !== undefined ? __('Right now this hides') : sprintf(__('In the last %d days this covers'), impact.days)}{' '}
      {impact.pageviews ? <strong>{sprintf(__('%s page views'), num(impact.pageviews))}</strong> : impact.events ? <strong>{sprintf(__('%s interactions'), num(impact.events))}</strong> : impact.findings ? <strong>{sprintf(__('%s findings'), num(impact.findings))}</strong> : <strong>{__('nothing recorded')}</strong>}
      {impact.samples.length ? <span>: {impact.samples.slice(0, 3).map((s, i) => <code key={i}>{s}</code>)}</span> : null}.
    </p>
  ) : null;

  return (
    <div className="uf-modal-bg" onClick={(e) => { if (e.target === e.currentTarget) { close(); } }}>
      <div className="uf-modal uf-excl-modal" role="dialog" aria-modal="true" aria-labelledby="uf-excl-title" tabIndex={-1} ref={box}>
        <div className="uf-modal-top">
          <div><h2 id="uf-excl-title">{__('Exclude')}</h2><p className="uf-excl-subject"><code>{p.label}</code></p></div>
          <button type="button" className="button" onClick={close} aria-label={__('Close')}>×</button>
        </div>
        {saved ? (
          <div>
            <p role="status"><strong>{__('The rule is saved and applies from now on.')}</strong></p>
            <p><a href={cfg().urls.exclusions || '#'}>{__('See all exclusions')}</a></p>
            <p><button type="button" className="button button-primary" onClick={close}>{__('Done')}</button></p>
          </div>
        ) : (
          <div>
            {scopes.length > 1 ? (
              <fieldset className="uf-excl-set"><legend>{__('What should be excluded?')}</legend>
                {scopes.map((s) => (
                  <label key={s.id} className="uf-excl-opt"><input type="radio" name="uf-excl-scope" checked={scope === s.id} onChange={() => setScope(s.id)} /> <span><strong>{s.label}</strong>{s.hint ? <small><code>{s.hint}</code></small> : null}</span></label>
                ))}
              </fieldset>
            ) : null}
            {isFinding ? null : (
              <fieldset className="uf-excl-set"><legend>{__('What should happen?')}</legend>
                {(['stats', 'stop'] as Mode[]).concat(audit ? ['audit' as Mode] : []).map((m) => (
                  <label key={m} className="uf-excl-opt"><input type="radio" name="uf-excl-mode" checked={mode === m} onChange={() => setMode(m)} /> <span><strong>{MODE_LABEL[m]}</strong></span></label>
                ))}
              </fieldset>
            )}
            <p className="uf-excl-effect" aria-live="polite">{EFFECT[mode]}{p.subject.kind === 'page' && mode === 'stats' ? ' ' + __('Visits and visitors are not changed; page views and the interactions on that page are.') : ''}</p>
            {effect}
            <label className="uf-excl-note">{__('Note (optional)')}<input type="text" maxLength={300} value={note} onInput={(e) => setNote((e.target as HTMLInputElement).value)} placeholder={__('Why is this excluded?')} /></label>
            {err ? <p className="uf-error" role="alert">{err}</p> : null}
            <p className="uf-excl-actions">
              <button type="button" className="button button-primary" disabled={busy || !chosen} onClick={confirm}>{busy ? __('Saving…') : __('Exclude')}</button>{' '}
              <button type="button" className="button" onClick={p.onClose}>{__('Cancel')}</button>
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
