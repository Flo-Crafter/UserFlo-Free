import { h, Fragment, __, useState, rangePresets, sprintf } from '../wp';
import { useApi } from '../hooks';
import { send } from '../api';
import { slots } from '../slots';
import type { Goal, GoalBreakdown, GoalList, GoalStats } from '../types';
import { num } from '../format';
import { Async, Card, DeltaChip, Empty, LimitNote } from '../components/ui';

type Draft = { id: number; name: string; type: 'page' | 'event'; match_type: string; pattern: string; event_type: string; min_count: string };
const BLANK: Draft = { id: 0, name: '', type: 'page', match_type: 'exact', pattern: '', event_type: 'form', min_count: '1' };
const fromGoal = (g: Goal): Draft => ({ id: g.id, name: g.name, type: g.type === 'event' ? 'event' : 'page', match_type: g.match_type, pattern: g.pattern, event_type: g.event_type || 'form', min_count: String(g.min_count || 1) });
const rule = (g: Goal): string => {
  let r = g.type === 'page' ? (g.match_type === 'exact' ? __('Page is') : g.match_type === 'prefix' ? __('Page starts with') : __('Page contains')) + ' ' + g.pattern : g.event_type + (g.pattern ? ': ' + g.pattern : '');
  if (g.min_count > 1) { r += ' · ' + sprintf(__('%d times in a session'), g.min_count); }
  return r;
};
const pct = (v: number | null) => (v === null ? '—' : v + '%');

function GoalForm(p: { initial: Draft; list: GoalList; onDone: () => void; onCancel: () => void }) {
  const [d, setD] = useState<Draft>(p.initial);
  const [err, setErr] = useState('');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const set = (patch: Partial<Draft>) => { setD((o) => ({ ...o, ...patch })); setNote(''); };
  const body = () => ({ name: d.name, type: d.type, match_type: d.match_type, pattern: d.pattern, event_type: d.event_type, status: 'active', min_count: Number(d.min_count) || 1 });
  const check = async () => {
    setErr(''); setBusy(true);
    try {
      const r = await send<{ matches: number | null; sessions: number | null; days?: number; duplicate_name: boolean }>('POST', 'goals/preview', body());
      setNote(r.matches === null ? __('This looks valid.') : sprintf(__('In the last %1$d days this would have matched %2$s page views or events in %3$s sessions.'), r.days || 30, num(r.matches), num(r.sessions || 0)) + (r.duplicate_name ? ' ' + __('Another goal already has this name.') : ''));
    } catch (e) { setErr((e as Error).message); }
    setBusy(false);
  };
  const save = async () => {
    setErr(''); setBusy(true);
    try { await send(d.id ? 'PUT' : 'POST', d.id ? 'goals/' + d.id : 'goals', body()); p.onDone(); } catch (e) { setErr((e as Error).message); setBusy(false); }
  };
  return (
    <Card title={d.id ? __('Edit goal') : __('New goal')}>
      <div className="uf-vf-grid">
        <label>{__('Name')}<input type="text" value={d.name} maxLength={100} onInput={(e) => set({ name: (e.target as HTMLInputElement).value })} /></label>
        <label>{__('Measure')}
          <select value={d.type} onChange={(e) => set({ type: (e.target as HTMLSelectElement).value as Draft['type'], match_type: 'exact' })}>
            <option value="page">{__('A page is viewed')}</option><option value="event">{__('An event happens')}</option>
          </select></label>
        {d.type === 'page' ? (<>
          <label>{__('Page address')}
            <select value={d.match_type} onChange={(e) => set({ match_type: (e.target as HTMLSelectElement).value })}>
              <option value="exact">{__('is exactly')}</option><option value="prefix">{__('starts with')}</option><option value="contains">{__('contains')}</option>
            </select></label>
          <label>{__('Path')}<input type="text" value={d.pattern} placeholder="/thank-you/" maxLength={300} onInput={(e) => set({ pattern: (e.target as HTMLInputElement).value })} />
            <small>{__('The path only, starting with a slash. The query string is ignored and upper/lower case does not matter.')}</small></label>
        </>) : (<>
          <label>{__('Event type')}
            <select value={d.event_type} onChange={(e) => set({ event_type: (e.target as HTMLSelectElement).value })}>{p.list.event_types.map((t) => <option key={t} value={t}>{t}</option>)}</select></label>
          <label>{__('Name')}
            <select value={d.match_type} onChange={(e) => set({ match_type: (e.target as HTMLSelectElement).value })}><option value="exact">{__('is exactly')}</option><option value="contains">{__('contains')}</option></select></label>
          <label>{d.event_type === 'download' || d.event_type === 'outbound' ? __('File path or address') : __('Event name')}<input type="text" value={d.pattern} maxLength={300} onInput={(e) => set({ pattern: (e.target as HTMLInputElement).value })} />
            <small>{__('Leave empty to count any event of this type.')}</small></label>
        </>)}
        <label>{__('Count as completed after this many times in one session')}
          <input type="number" min="1" max="1000" step="1" value={d.min_count} onInput={(e) => set({ min_count: (e.target as HTMLInputElement).value })} />
          <small>{__('1 completes the goal the first time. Use 3 to count a visit that opens the page three times, for example.')}</small></label>
      </div>
      <LimitNote feature="goals_advanced">{__('A value per goal, a time frame, finishing within some minutes, and targets for a weekday and time window (for example “more than 500 downloads on Tuesdays”) are part of Pro.')}</LimitNote>
      {err ? <div className="notice notice-error inline" role="alert"><p>{err}</p></div> : null}
      {note ? <p className="uf-sub" role="status">{note}</p> : null}
      <p><button className="button button-primary" disabled={busy} onClick={save}>{__('Save')}</button> <button className="button" disabled={busy} onClick={check}>{__('Check')}</button> <button className="button" onClick={p.onCancel}>{__('Cancel')}</button></p>
    </Card>
  );
}

function Breakdown(p: { goal: Goal; days: number }) {
  const [by, setBy] = useState('campaign');
  const dims: [string, string][] = [['campaign', __('Campaign')], ['source', __('Source')]];
  const q = useApi<GoalBreakdown>('goals/' + p.goal.id + '/breakdown', { days: p.days, by });
  return (
    <Card title={sprintf(__('%s by…'), p.goal.name)}>
      <div className="uf-filter" role="group" aria-label={__('Split by')}>
        {dims.map(([k, l]) => <button key={k} className={'button' + (by === k ? ' button-primary' : '')} aria-pressed={by === k} onClick={() => setBy(k)}>{l}</button>)}
      </div>
      <LimitNote feature="goals_advanced">{__('Splitting by device, country and new / returning visitors is part of Pro.')}</LimitNote>
      <Async q={q} empty={(d) => d.rows.length === 0} emptyText={__('No sessions in this period.')}>
        {(d) => (
          <table className="widefat striped uf-compact"><thead><tr><th>{__('Value')}</th><th>{__('Sessions')}</th><th>{__('Completions')}</th><th>{__('Conversion rate')}</th></tr></thead>
            <tbody>{d.rows.map((r) => <tr key={r.key}><td>{r.key || __('(none)')}</td><td>{num(r.sessions)}</td><td>{num(r.completions)}</td><td>{pct(r.rate)}</td></tr>)}</tbody></table>
        )}
      </Async>
    </Card>
  );
}

/** Goals: define outcomes, see completions and conversion rates. With UserFlo Pro the complete screen (funnels, targets and more) replaces this one. */
export function Goals() {
  const Full = slots().GoalsFull;
  if (Full) { return <Full />; }
  return <GoalsBasic />;
}

function GoalsBasic() {
  const [days, setDays] = useState(30);
  const list = useApi<GoalList>('goals');
  const stats = useApi<GoalStats>('goals/stats', { days });
  const [edit, setEdit] = useState<Draft | null>(null);
  const [focus, setFocus] = useState<number>(0);
  const [msg, setMsg] = useState('');
  const presets = rangePresets([[7, __('7 days')], [30, __('30 days')]]);
  const done = () => { setEdit(null); list.reload(); stats.reload(); };
  const act = async (fn: () => Promise<unknown>) => { setMsg(''); try { await fn(); done(); } catch (e) { setMsg((e as Error).message); } };
  const rebuild = async (id: number) => {
    setMsg(__('Reading history…'));
    try {
      let after = 0; let added = 0;
      for (let i = 0; i < 400; i++) {
        const r = await send<{ next: number; done: boolean; added: number }>('POST', `goals/${id}/rebuild`, { after });
        added += r.added; after = r.next;
        if (r.done) { break; }
      }
      setMsg(sprintf(__('Done. %s sessions were recorded for this goal from your stored history.'), num(added))); stats.reload();
    } catch (e) { setMsg((e as Error).message); }
  };
  return (
    <div>
      <h1 className="uf-title">{__('Goals')}</h1>
      <p className="uf-sub">{__('A goal is an outcome you care about. A session completes a goal at most once. The conversion rate is the sessions that completed it divided by all sessions that started in the period.')}</p>
      <div className="uf-filter" role="group" aria-label={__('Date range')}>
        {presets.map(([d, l]) => <button key={d} className={'button' + (days === d ? ' button-primary' : '')} onClick={() => setDays(d)}>{l}</button>)}
      </div>
      {msg ? <p className="uf-sub" role="status">{msg}</p> : null}
      <Async q={list}>
        {(l) => (
          <div>
            <p><button className="button button-primary" onClick={() => setEdit({ ...BLANK })} disabled={l.limit > 0 && l.active >= l.limit}>{__('Add a goal')}</button></p>
            {l.limit > 0 ? <p className="uf-sub">{sprintf(__('%1$d of %2$d active goals used.'), l.active, l.limit)}</p> : null}
            <LimitNote feature="goals_advanced">{__('A value per goal, funnels, targets and splitting by device or country are part of Pro.')}</LimitNote>
            {edit ? <GoalForm initial={edit} list={l} onDone={done} onCancel={() => setEdit(null)} /> : null}
            <Async q={stats}>
              {(s) => (
                <div>
                  <p className="uf-sub">{num(s.converting)} {__('of')} {num(s.sessions)} {__('sessions in this period completed at least one goal.')}</p>
                  {s.goals.length === 0 ? <Empty>{__('No goals yet. Add one to start measuring outcomes such as a thank-you page or a form submission.')}</Empty> : (
                    <div className="uf-scroll"><table className="widefat striped uf-table">
                      <thead><tr><th>{__('Goal')}</th><th>{__('Completions')}</th><th>{__('Conversion rate')}</th><th>{__('Status')}</th><th>{__('Actions')}</th></tr></thead>
                      <tbody>{s.goals.map((g) => {
                        const def = l.goals.find((x) => x.id === g.id);
                        if (!def) { return null; } // The two requests finish separately; a goal deleted a moment ago can be in one and not the other.
                        return (
                          <tr key={g.id}>
                            <td><strong>{g.name}</strong><div><small>{rule(def)}</small></div></td>
                            <td>{num(g.completions)} {g.prev !== undefined ? <DeltaChip now={g.completions} before={g.prev} vs={__('previous period')} /> : null}</td>
                            <td>{pct(g.rate)}</td>
                            <td>{g.status === 'paused' ? __('Paused') : __('Active')}</td>
                            <td>
                              <button className="button-link" onClick={() => setEdit(fromGoal(def))}>{__('Edit')}</button>{' · '}
                              <button className="button-link" onClick={() => act(() => send('PUT', 'goals/' + def.id, { ...def, status: def.status === 'active' ? 'paused' : 'active' }))}>{def.status === 'active' ? __('Pause') : __('Resume')}</button>{' · '}
                              <button className="button-link" onClick={() => setFocus(focus === def.id ? 0 : def.id)} aria-expanded={focus === def.id}>{__('Split')}</button>{' · '}
                              <button className="button-link" onClick={() => rebuild(def.id)}>{__('Re-read history')}</button>{' · '}
                              <button className="button-link uf-danger" onClick={() => { if (window.confirm(__('Delete this goal and all of its recorded completions?'))) { act(() => send('DELETE', 'goals/' + def.id)); } }}>{__('Delete')}</button>
                            </td>
                          </tr>);
                      })}</tbody>
                    </table></div>
                  )}
                  {focus && l.goals.find((x) => x.id === focus) ? <Breakdown goal={l.goals.find((x) => x.id === focus)!} days={days} /> : null}
                </div>
              )}
            </Async>
          </div>
        )}
      </Async>
    </div>
  );
}
