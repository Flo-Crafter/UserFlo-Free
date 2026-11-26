import { h, __, useState, useMemo, sprintf } from '../wp';
import { useApi } from '../hooks';
import { send } from '../api';
import type { ExclusionImpact, ExclusionList, ExclusionRule } from '../types';
import { dateTime, num } from '../format';
import { Async, Badge, Card, Empty } from '../components/ui';
import { MODE_LABEL, describeRule } from '../components/Exclude';

type Mode = 'stop' | 'stats' | 'audit';
type Draft = {
  id: number; kind: 'page' | 'element' | 'finding'; mode: Mode; field: string; match_type: string; pattern: string; event_type: string; page_scope: string;
  finding_type: string; note: string; status: 'active' | 'paused'; label: string;
};
const BLANK: Draft = { id: 0, kind: 'page', mode: 'stats', field: 'target', match_type: 'exact', pattern: '', event_type: '', page_scope: '', finding_type: '', note: '', status: 'active', label: '' };
const fromRule = (r: ExclusionRule): Draft => ({ id: r.id, kind: r.target, mode: r.mode, field: r.field, match_type: r.match_type, pattern: r.pattern, event_type: r.event_type, page_scope: r.page_scope, finding_type: r.finding_type, note: r.note, status: r.status, label: r.label });
const toBody = (d: Draft): Record<string, string> => ({
  mode: d.mode, target: d.kind, field: d.kind === 'page' ? 'url' : d.field, match_type: d.field === 'css' && d.kind === 'element' ? 'exact' : d.match_type, pattern: d.pattern,
  event_type: d.kind === 'element' ? d.event_type : '', page_scope: d.kind === 'page' ? '' : d.page_scope, finding_type: d.kind === 'finding' ? d.finding_type : '', label: d.label, note: d.note, status: d.status,
});
const ruleBody = (r: ExclusionRule, status: string): Record<string, string> => ({ ...toBody(fromRule(r)), status });

function effectText(i: ExclusionImpact): string {
  if (!i.known) { return __('This kind of rule is applied in the visitor’s browser or by the audit, so there is nothing to count yet.'); }
  const n = i.pageviews || i.events;
  const what = i.pageviews ? __('page views') : __('interactions');
  return n ? sprintf(__('In the last %1$d days it covered %2$s %3$s.'), i.days, num(n), what) : sprintf(__('In the last %d days it covered nothing recorded.'), i.days);
}

function RuleForm(p: { initial: Draft; audit: boolean; onDone: () => void; onCancel: () => void }) {
  const [d, setD] = useState<Draft>(p.initial);
  const [err, setErr] = useState('');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const set = (patch: Partial<Draft>) => { setD((o) => ({ ...o, ...patch })); setNote(''); };
  const css = d.kind === 'element' && d.field === 'css';
  // A CSS selector can only stop tracking, and an audit rule is about findings.
  const kindChange = (kind: Draft['kind']) => set({ kind, mode: kind === 'finding' ? 'audit' : d.mode === 'audit' ? 'stats' : d.mode, field: 'target' });
  const check = async () => {
    setErr(''); setBusy(true);
    try { const r = await send<ExclusionImpact>('POST', 'exclusions/preview', toBody(d)); setNote(effectText(r) + (r.samples.length ? ' ' + r.samples.slice(0, 3).join(', ') : '')); } catch (e) { setErr((e as Error).message); }
    setBusy(false);
  };
  const save = async () => {
    setErr(''); setBusy(true);
    try { await send(d.id ? 'PUT' : 'POST', d.id ? 'exclusions/' + d.id : 'exclusions', toBody(d)); p.onDone(); } catch (e) { setErr((e as Error).message); setBusy(false); }
  };
  const modes: Mode[] = d.kind === 'finding' ? ['audit'] : css ? ['stop'] : ['stats', 'stop'];
  return (
    <Card title={d.id ? __('Edit exclusion') : __('New exclusion')}>
      <div className="uf-vf-grid">
        <label>{__('Applies to')}
          <select value={d.kind} onChange={(e) => kindChange((e.target as HTMLSelectElement).value as Draft['kind'])}>
            <option value="page">{__('A page')}</option><option value="element">{__('An interaction (click, link, download, form…)')}</option>
            {p.audit ? <option value="finding">{__('An audit finding')}</option> : null}
          </select></label>
        {d.kind === 'element' ? (
          <label>{__('Recognise it by')}
            <select value={d.field} onChange={(e) => set({ field: (e.target as HTMLSelectElement).value, mode: (e.target as HTMLSelectElement).value === 'css' ? 'stop' : d.mode })}>
              <option value="target">{__('Element identifier')}</option><option value="name">{__('Label')}</option><option value="value">{__('Link address or file')}</option>
              <option value="component">{__('Section it sits in')}</option><option value="css">{__('CSS selector (stops tracking only)')}</option>
            </select></label>
        ) : null}
        {d.kind === 'finding' ? <label>{__('Type of finding')}<input type="text" value={d.finding_type} maxLength={40} placeholder="img_alt_missing" onInput={(e) => set({ finding_type: (e.target as HTMLInputElement).value })} /><small>{__('Leave empty to cover every type on the pages below.')}</small></label> : null}
        {!css ? (
          <label>{__('Match')}
            <select value={d.match_type} onChange={(e) => set({ match_type: (e.target as HTMLSelectElement).value })}>
              <option value="exact">{__('is exactly')}</option><option value="prefix">{__('starts with')}</option><option value="contains">{__('contains')}</option>
            </select></label>
        ) : null}
        <label>{d.kind === 'page' ? __('Page path') : css ? __('CSS selector') : d.kind === 'finding' ? __('Element (optional)') : __('Value')}
          <input type="text" value={d.pattern} maxLength={300} placeholder={d.kind === 'page' ? '/members/' : css ? '.cookie-banner a' : ''} onInput={(e) => set({ pattern: (e.target as HTMLInputElement).value })} />
          <small>{d.kind === 'page' ? __('The path only, starting with a slash. The query string is ignored and upper/lower case does not matter.') : css ? __('A class, an id or a data attribute. Complex selectors such as :has() or :not() are refused.') : __('Upper/lower case does not matter. There are no wildcards or regular expressions.')}</small></label>
        {d.kind !== 'page' ? <label>{__('Only on pages under (optional)')}<input type="text" value={d.page_scope} maxLength={300} placeholder="/shop/" onInput={(e) => set({ page_scope: (e.target as HTMLInputElement).value })} /></label> : null}
        {d.kind === 'element' && !css ? (
          <label>{__('Kind of interaction')}
            <select value={d.event_type} onChange={(e) => set({ event_type: (e.target as HTMLSelectElement).value })}>
              <option value="">{__('Any')}</option>
              {['click', 'download', 'outbound', 'form', 'media', 'custom', 'commerce'].map((t) => <option key={t} value={t}>{t}</option>)}
            </select></label>
        ) : null}
        <label>{__('Note (optional)')}<input type="text" value={d.note} maxLength={300} onInput={(e) => set({ note: (e.target as HTMLInputElement).value })} /></label>
      </div>
      <fieldset className="uf-excl-set"><legend>{__('What should happen?')}</legend>
        {modes.map((m) => <label key={m} className="uf-excl-opt"><input type="radio" name="uf-rule-mode" checked={d.mode === m} onChange={() => set({ mode: m })} /> <span><strong>{MODE_LABEL[m]}</strong></span></label>)}
      </fieldset>
      {note ? <p className="notice notice-info inline" role="status"><span>{note}</span></p> : null}
      {err ? <p className="uf-error" role="alert">{err}</p> : null}
      <p>
        {!css && d.kind !== 'finding' ? <button type="button" className="button" disabled={busy} onClick={check}>{__('Check what it covers')}</button> : null}{' '}
        <button type="button" className="button button-primary" disabled={busy} onClick={save}>{d.id ? __('Save changes') : __('Add exclusion')}</button>{' '}
        <button type="button" className="button" onClick={p.onCancel}>{__('Cancel')}</button>
      </p>
    </Card>
  );
}

function Row(p: { r: ExclusionRule; onChange: () => void; onEdit: (r: ExclusionRule) => void }) {
  const { r } = p;
  const [effect, setEffect] = useState('');
  const [confirm, setConfirm] = useState(false);
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const act = async (fn: () => Promise<unknown>) => { setBusy(true); setErr(''); try { await fn(); p.onChange(); } catch (e) { setErr((e as Error).message); } setBusy(false); };
  const showEffect = () => act(async () => { const i = await send<ExclusionImpact>('POST', 'exclusions/preview', ruleBody(r, r.status)); setEffect(effectText(i)); }).then(() => undefined);
  return (
    <tr className={r.status === 'paused' ? 'uf-excl-paused' : ''}>
      <td><span className="uf-strong">{describeRule(r)}</span>{r.label ? <div><small>{r.label}</small></div> : null}{r.note ? <div><small>{r.note}</small></div> : null}</td>
      <td>{MODE_LABEL[r.mode]}</td>
      <td>{r.status === 'active' ? <Badge kind="ret">{__('Active')}</Badge> : <Badge>{__('Paused')}</Badge>}</td>
      <td>{effect ? <small>{effect}</small> : <button type="button" className="button-link" disabled={busy} onClick={showEffect}>{__('Show effect')}</button>}</td>
      <td>{dateTime(r.created_at)}</td>
      <td className="uf-excl-actions-cell">
        {confirm ? (
          <span role="group" aria-label={__('Confirm delete')}>
            <small>{__('Delete this rule? Data already recorded is not deleted.')}</small>{' '}
            <button type="button" className="button button-small" disabled={busy} onClick={() => act(() => send('DELETE', 'exclusions/' + r.id))}>{__('Delete')}</button>{' '}
            <button type="button" className="button button-small" onClick={() => setConfirm(false)}>{__('Keep')}</button>
          </span>
        ) : (
          <span>
            <button type="button" className="button button-small" disabled={busy} onClick={() => act(() => send('PUT', 'exclusions/' + r.id, ruleBody(r, r.status === 'active' ? 'paused' : 'active')))}>{r.status === 'active' ? __('Pause') : __('Resume')}</button>{' '}
            <button type="button" className="button button-small" onClick={() => p.onEdit(r)}>{__('Edit')}</button>{' '}
            <button type="button" className="button button-small" onClick={() => setConfirm(true)}>{__('Delete')}</button>
          </span>
        )}
        {err ? <div className="uf-error" role="alert"><small>{err}</small></div> : null}
      </td>
    </tr>
  );
}

export function Exclusions() {
  const q = useApi<ExclusionList>('exclusions');
  const [form, setForm] = useState<Draft | null>(null);
  const [search, setSearch] = useState('');
  const [mode, setMode] = useState('');
  const [status, setStatus] = useState('');
  const done = () => { setForm(null); q.reload(); };
  const rules = useMemo(() => (q.data ? q.data.rules : []).filter((r) => (!mode || r.mode === mode) && (!status || r.status === status) && (!search || (describeRule(r) + ' ' + r.note + ' ' + r.label).toLowerCase().indexOf(search.toLowerCase()) > -1)), [q.data, mode, status, search]);
  return (
    <div>
      <h1 className="uf-title">{__('Exclusions')}</h1>
      <p className="uf-sub">{__('Decide which pages and interactions are tracked, which are counted in your statistics, and which audit findings you have already accepted. One list, used by every report.')}</p>
      <details className="uf-excl-help">
        <summary>{__('How the three kinds of exclusion differ')}</summary>
        <ul>
          <li><strong>{MODE_LABEL.stop}.</strong> {__('Nothing is recorded from now on. Data already collected stays and is still counted unless you also leave it out of statistics.')}</li>
          <li><strong>{MODE_LABEL.stats}.</strong> {__('Collection continues (and stays subject to your privacy settings and retention), but the data is left out of totals, rankings, rates, trends, reports, exports and journeys. Pausing or deleting the rule counts it again; nothing is ever deleted.')}</li>
          <li><strong>{MODE_LABEL.audit}.</strong> {__('The scan and the inventory are untouched; matching findings stop counting as issues and are listed as excluded.')}</li>
        </ul>
        <p>{__('Rules for a page or interaction apply to visitors who are logged in as well as anonymous visitors. Excluding something never switches consent off or collects anything that would not be collected otherwise.')}</p>
      </details>
      <Async q={q}>
        {(d) => (
          <div>
            {form ? <RuleForm initial={form} audit={d.audit_available} onDone={done} onCancel={() => setForm(null)} /> : null}
            <div className="uf-filter">
              <button type="button" className="button button-primary" onClick={() => setForm({ ...BLANK })}>{__('Add exclusion')}</button>
              <label className="screen-reader-text" htmlFor="uf-excl-search">{__('Search exclusions')}</label>
              <input id="uf-excl-search" type="search" className="regular-text" placeholder={__('Search rules…')} value={search} onInput={(e) => setSearch((e.target as HTMLInputElement).value)} />
              <label className="screen-reader-text" htmlFor="uf-excl-mode">{__('Show')}</label>
              <select id="uf-excl-mode" value={mode} onChange={(e) => setMode((e.target as HTMLSelectElement).value)}>
                <option value="">{__('All kinds')}</option><option value="stop">{MODE_LABEL.stop}</option><option value="stats">{MODE_LABEL.stats}</option>{d.audit_available ? <option value="audit">{MODE_LABEL.audit}</option> : null}
              </select>
              <label className="screen-reader-text" htmlFor="uf-excl-status">{__('Status')}</label>
              <select id="uf-excl-status" value={status} onChange={(e) => setStatus((e.target as HTMLSelectElement).value)}>
                <option value="">{__('Any status')}</option><option value="active">{__('Active')}</option><option value="paused">{__('Paused')}</option>
              </select>
            </div>
            {d.limit ? <p className="uf-limit-note">{sprintf(__('%1$d of %2$d active rules used.'), d.active, d.limit)}</p> : null}
            {d.rules.length === 0 ? <Empty>{__('No exclusions yet. Use the exclude button at the end of a row on Pages or Statistics, or add one here.')}</Empty> : rules.length === 0 ? <Empty>{__('No rules match.')}</Empty> : (
              <div className="uf-scroll"><table className="widefat striped uf-table">
                <thead><tr><th scope="col">{__('Rule')}</th><th scope="col">{__('What it does')}</th><th scope="col">{__('Status')}</th><th scope="col">{__('Effect')}</th><th scope="col">{__('Added')}</th><th scope="col"><span className="screen-reader-text">{__('Actions')}</span></th></tr></thead>
                <tbody>{rules.map((r) => <Row key={r.id} r={r} onChange={q.reload} onEdit={(x) => setForm(fromRule(x))} />)}</tbody>
              </table></div>
            )}
            {d.builtin.length ? (
              <Card title={__('Set in Settings → Privacy')}>
                <p className="uf-sub">{__('These older exclusions keep working exactly as before. Change them in Settings.')} <a href={d.settings_url}>{__('Open Settings')}</a></p>
                <ul className="uf-excl-builtin">{d.builtin.map((b, i) => <li key={i}><Badge>{b.kind === 'path' ? __('Page prefix') : b.kind === 'role' ? __('User role') : __('CSS selector')}</Badge> <code>{b.label}</code></li>)}</ul>
              </Card>
            ) : null}
          </div>
        )}
      </Async>
    </div>
  );
}
